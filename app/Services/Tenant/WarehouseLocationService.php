<?php

namespace App\Services\Tenant;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WarehouseLocationService
{
    public function list(User $user, ?int $warehouseId = null): Collection
    {
        $query = WarehouseLocation::query()
            ->whereHas('warehouse', fn ($builder) => $builder->where('tenant_id', $user->tenant_id))
            ->with([
                'warehouse:id,name',
                'parent:id,name,code,warehouse_id',
            ])
            ->withCount(['children', 'productStorage'])
            ->orderBy('warehouse_id')
            ->orderBy('type')
            ->orderBy('code');

        if ($warehouseId !== null && $warehouseId > 0) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }

    public function formOptions(User $user): array
    {
        $warehouses = Warehouse::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active']);

        $locations = WarehouseLocation::query()
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->with('warehouse:id,name')
            ->orderBy('warehouse_id')
            ->orderBy('type')
            ->orderBy('code')
            ->get([
                'id',
                'warehouse_id',
                'parent_id',
                'code',
                'name',
                'type',
                'is_active',
            ]);

        return [
            'warehouses' => $warehouses,
            'locations' => $locations,
        ];
    }

    public function create(User $user, array $data): WarehouseLocation
    {
        $warehouseId = (int) Arr::get($data, 'warehouse_id');
        $this->guardWarehouseBelongsToTenant($user, $warehouseId);

        $parentId = Arr::get($data, 'parent_id');
        if ($parentId !== null) {
            $this->guardParentLocation($user, $warehouseId, (int) $parentId);
        }

        return WarehouseLocation::query()->create([
            'warehouse_id' => $warehouseId,
            'code' => Arr::get($data, 'code'),
            'name' => Arr::get($data, 'name'),
            'type' => Arr::get($data, 'type'),
            'parent_id' => $parentId,
            'capacity' => Arr::get($data, 'capacity'),
            'description' => Arr::get($data, 'description'),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
        ]);
    }

    public function update(User $user, WarehouseLocation $location, array $data): WarehouseLocation
    {
        $this->guardLocationBelongsToTenant($user, $location);

        $warehouseId = array_key_exists('warehouse_id', $data)
            ? (int) Arr::get($data, 'warehouse_id')
            : (int) $location->warehouse_id;

        $this->guardWarehouseBelongsToTenant($user, $warehouseId);

        $parentId = array_key_exists('parent_id', $data)
            ? Arr::get($data, 'parent_id')
            : $location->parent_id;

        if ($parentId !== null) {
            $this->guardParentLocation($user, $warehouseId, (int) $parentId, $location);
        }

        $location->fill(Arr::only($data, [
            'code',
            'name',
            'type',
            'capacity',
            'description',
            'is_active',
        ]));

        $location->warehouse_id = $warehouseId;
        $location->parent_id = $parentId;
        $location->save();

        return $location->refresh();
    }

    public function delete(User $user, WarehouseLocation $location): void
    {
        $this->guardLocationBelongsToTenant($user, $location);

        if ($location->children()->exists()) {
            throw ValidationException::withMessages([
                'warehouse_location' => 'Không thể xóa vị trí đang có vị trí con.',
            ]);
        }

        if ($location->productStorage()->exists() || $location->stocks()->exists()) {
            throw ValidationException::withMessages([
                'warehouse_location' => 'Không thể xóa vị trí đã phát sinh tồn kho hoặc đã gán sản phẩm.',
            ]);
        }

        $location->delete();
    }

    private function guardWarehouseBelongsToTenant(User $user, int $warehouseId): void
    {
        $exists = Warehouse::query()
            ->where('id', $warehouseId)
            ->where('tenant_id', $user->tenant_id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Kho không thuộc tenant hiện tại.',
            ]);
        }
    }

    private function guardLocationBelongsToTenant(User $user, WarehouseLocation $location): void
    {
        $belongs = Warehouse::query()
            ->where('id', $location->warehouse_id)
            ->where('tenant_id', $user->tenant_id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'warehouse_location' => 'Vị trí kho không thuộc tenant hiện tại.',
            ]);
        }
    }

    private function guardParentLocation(User $user, int $warehouseId, int $parentId, ?WarehouseLocation $current = null): void
    {
        $parent = WarehouseLocation::query()->find($parentId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Vị trí cha không tồn tại.',
            ]);
        }

        $this->guardLocationBelongsToTenant($user, $parent);

        if ((int) $parent->warehouse_id !== $warehouseId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Vị trí cha phải cùng kho với vị trí hiện tại.',
            ]);
        }

        if ($current !== null) {
            if ((int) $current->id === (int) $parent->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Không thể chọn chính nó làm vị trí cha.',
                ]);
            }

            $ancestor = $parent;
            $visited = [];
            while ($ancestor->parent_id !== null) {
                $nextId = (int) $ancestor->parent_id;
                if (isset($visited[$nextId])) {
                    break;
                }
                $visited[$nextId] = true;

                if ($nextId === (int) $current->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Không thể chọn vị trí con làm vị trí cha (vòng lặp cây).',
                    ]);
                }

                $ancestor = WarehouseLocation::query()->find($nextId);
                if ($ancestor === null) {
                    break;
                }
            }
        }
    }
}
