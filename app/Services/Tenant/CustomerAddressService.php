<?php

namespace App\Services\Tenant;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerAddressService
{
    public function list(User $user, Customer $customer): Collection
    {
        $this->guardTenantCustomer($user, $customer);

        return CustomerAddress::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('default_address')
            ->orderByDesc('id')
            ->get();
    }

    public function create(User $user, Customer $customer, array $data): CustomerAddress
    {
        $this->guardTenantCustomer($user, $customer);

        $isDefault = $this->normalizeDefaultAddress(Arr::get($data, 'default_address', 'NO'));

        if ($isDefault === 'YES') {
            CustomerAddress::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('customer_id', $customer->id)
                ->update(['default_address' => 'NO']);
        }

        return CustomerAddress::query()->create([
            'tenant_id' => $user->tenant_id,
            'customer_id' => $customer->id,
            'name' => Arr::get($data, 'name'),
            'address' => Arr::get($data, 'address'),
            'receiver_name' => Arr::get($data, 'receiver_name'),
            'receiver_phone' => Arr::get($data, 'receiver_phone'),
            'default_address' => $isDefault,
        ]);
    }

    public function update(User $user, Customer $customer, CustomerAddress $customerAddress, array $data): CustomerAddress
    {
        $this->guardTenantCustomer($user, $customer);
        $this->guardAddressBelongsToCustomer($user, $customer, $customerAddress);

        if (array_key_exists('default_address', $data)) {
            $isDefault = $this->normalizeDefaultAddress(Arr::get($data, 'default_address', 'NO'));
            if ($isDefault === 'YES') {
                CustomerAddress::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('customer_id', $customer->id)
                    ->where('id', '!=', $customerAddress->id)
                    ->update(['default_address' => 'NO']);
            }
            $customerAddress->default_address = $isDefault;
        }

        $customerAddress->fill(Arr::only($data, [
            'name',
            'address',
            'receiver_name',
            'receiver_phone',
        ]));
        $customerAddress->save();

        return $customerAddress->refresh();
    }

    public function delete(User $user, Customer $customer, CustomerAddress $customerAddress): void
    {
        $this->guardTenantCustomer($user, $customer);
        $this->guardAddressBelongsToCustomer($user, $customer, $customerAddress);

        $customerAddress->delete();
    }

    private function normalizeDefaultAddress(mixed $value): string
    {
        return strtoupper((string) $value) === 'YES' ? 'YES' : 'NO';
    }

    private function guardTenantCustomer(User $user, Customer $customer): void
    {
        if ((int) $customer->tenant_id !== (int) $user->tenant_id) {
            throw ValidationException::withMessages([
                'customer' => 'Customer does not belong to this tenant.',
            ]);
        }
    }

    private function guardAddressBelongsToCustomer(User $user, Customer $customer, CustomerAddress $customerAddress): void
    {
        if ((int) $customerAddress->tenant_id !== (int) $user->tenant_id || (int) $customerAddress->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'address' => 'Address does not belong to this customer.',
            ]);
        }
    }
}
