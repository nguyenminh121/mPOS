<?php

namespace App\Services\Tenant;

use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerGroupService
{
    public function list(User $user): Collection
    {
        return CustomerGroup::query()
            ->where('tenant_id', $user->tenant_id)
            ->with('defaultPriceList:id,name,code')
            ->withCount('customers')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'description',
                'default_price_list_id',
                'is_active',
            ]);
    }

    public function create(User $user, array $data): CustomerGroup
    {
        $this->assertPriceListBelongsToTenant($user, Arr::get($data, 'default_price_list_id'));

        return CustomerGroup::query()->create([
            ...Arr::only($data, ['code', 'name', 'description', 'default_price_list_id', 'is_active']),
            'tenant_id' => $user->tenant_id,
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, CustomerGroup $customerGroup, array $data): CustomerGroup
    {
        $this->guardTenant($user, $customerGroup);

        if (array_key_exists('default_price_list_id', $data)) {
            $this->assertPriceListBelongsToTenant($user, Arr::get($data, 'default_price_list_id'));
        }

        $customerGroup->fill(Arr::only($data, ['code', 'name', 'description', 'default_price_list_id', 'is_active']));
        $customerGroup->save();

        return $customerGroup->refresh();
    }

    public function delete(User $user, CustomerGroup $customerGroup): void
    {
        $this->guardTenant($user, $customerGroup);

        if ($customerGroup->customers()->exists()) {
            throw ValidationException::withMessages([
                'customer_group' => 'Không thể xóa nhóm đang có khách hàng.',
            ]);
        }

        $customerGroup->delete();
    }

    private function assertPriceListBelongsToTenant(User $user, mixed $priceListId): void
    {
        if ($priceListId === null) {
            return;
        }

        $exists = PriceList::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey((int) $priceListId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'default_price_list_id' => 'Bảng giá mặc định không tồn tại trong tenant.',
            ]);
        }
    }

    private function guardTenant(User $user, CustomerGroup $customerGroup): void
    {
        if ((int) $customerGroup->tenant_id !== (int) $user->tenant_id) {
            throw ValidationException::withMessages([
                'customer_group' => 'Customer group does not belong to this tenant.',
            ]);
        }
    }
}
