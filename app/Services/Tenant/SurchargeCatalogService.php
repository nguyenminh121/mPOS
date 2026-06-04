<?php

namespace App\Services\Tenant;

use App\Models\SurchargeCatalog;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SurchargeCatalogService
{
    public function list(User $user): Collection
    {
        return SurchargeCatalog::query()
            ->where('tenant_id', $user->tenant_id)
            ->with('creator:id,name')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function create(User $user, array $data): SurchargeCatalog
    {
        return SurchargeCatalog::query()->create([
            ...Arr::only($data, [
                'code',
                'name',
                'description',
                'type',
                'value',
                'is_auto_apply',
                'is_refundable',
                'is_active',
                'category',
            ]),
            'tenant_id' => $user->tenant_id,
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, SurchargeCatalog $surchargeCatalog, array $data): SurchargeCatalog
    {
        $this->guardTenant($user, $surchargeCatalog);

        $surchargeCatalog->fill(Arr::only($data, [
            'code',
            'name',
            'description',
            'type',
            'value',
            'is_auto_apply',
            'is_refundable',
            'is_active',
            'category',
        ]));

        $surchargeCatalog->save();

        return $surchargeCatalog->refresh();
    }

    public function delete(User $user, SurchargeCatalog $surchargeCatalog): void
    {
        $this->guardTenant($user, $surchargeCatalog);

        $surchargeCatalog->delete();
    }

    private function guardTenant(User $user, SurchargeCatalog $surchargeCatalog): void
    {
        if ((int) $surchargeCatalog->tenant_id !== (int) $user->tenant_id) {
            throw ValidationException::withMessages([
                'surcharge' => 'Surcharge does not belong to this tenant.',
            ]);
        }
    }
}
