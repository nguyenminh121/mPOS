<?php

namespace App\Services\Tenant;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    public function list(User $user): Collection
    {
        return Warehouse::query()
            ->where('tenant_id', $user->tenant_id)
            ->withCount(['stocks', 'importReceipts'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function create(User $user, array $data): Warehouse
    {
        $payload = Arr::only($data, ['code', 'name', 'address', 'phone', 'is_active', 'is_default']);
        $payload['tenant_id'] = $user->tenant_id;

        $warehouse = Warehouse::query()->create($payload);

        if ((bool) ($payload['is_default'] ?? false)) {
            $this->setDefaultWarehouse($user, $warehouse);
            return $warehouse->refresh();
        }

        if (!Warehouse::query()->where('tenant_id', $user->tenant_id)->where('is_default', true)->exists()) {
            $this->setDefaultWarehouse($user, $warehouse);
            return $warehouse->refresh();
        }

        return $warehouse;
    }

    public function update(User $user, Warehouse $warehouse, array $data): Warehouse
    {
        $this->guardTenant($user, $warehouse);

        $warehouse->fill(Arr::only($data, ['code', 'name', 'address', 'phone', 'is_active', 'is_default']));
        $warehouse->save();

        if ((bool) ($data['is_default'] ?? false)) {
            $this->setDefaultWarehouse($user, $warehouse);
        }

        if ($warehouse->is_default && ! $warehouse->is_active) {
            throw ValidationException::withMessages([
                'is_active' => 'Kho mặc định phải ở trạng thái hoạt động.',
            ]);
        }

        if (!Warehouse::query()->where('tenant_id', $user->tenant_id)->where('is_default', true)->exists()) {
            $fallback = Warehouse::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($fallback !== null) {
                $this->setDefaultWarehouse($user, $fallback);
            }
        }

        return $warehouse->refresh();
    }

    public function delete(User $user, Warehouse $warehouse): void
    {
        $this->guardTenant($user, $warehouse);

        if ($warehouse->is_default) {
            throw ValidationException::withMessages([
                'warehouse' => 'Không thể xóa kho mặc định.',
            ]);
        }

        if ($warehouse->stocks()->exists() || $warehouse->importReceipts()->exists()) {
            throw ValidationException::withMessages([
                'warehouse' => 'Không thể xóa kho đã phát sinh tồn kho hoặc phiếu nhập.',
            ]);
        }

        $warehouse->delete();
    }

    public function setDefaultWarehouse(User $user, Warehouse $warehouse): void
    {
        $this->guardTenant($user, $warehouse);

        Warehouse::query()
            ->where('tenant_id', $user->tenant_id)
            ->update(['is_default' => false]);

        // Update directly by id to avoid Eloquent dirty-check skipping update
        // when the in-memory model already has is_default=true.
        Warehouse::query()
            ->whereKey($warehouse->getKey())
            ->where('tenant_id', $user->tenant_id)
            ->update(['is_default' => true, 'is_active' => true]);

        $warehouse->forceFill(['is_default' => true, 'is_active' => true]);
        $warehouse->syncOriginal();
    }

    private function guardTenant(User $user, Warehouse $warehouse): void
    {
        if ($warehouse->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'warehouse' => 'Warehouse does not belong to this tenant.',
            ]);
        }
    }
}
