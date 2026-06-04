<?php

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function view(User $user, CustomerAddress $customerAddress): bool
    {
        return $this->isTenantMember($user, (int) $customerAddress->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function update(User $user, CustomerAddress $customerAddress): bool
    {
        return $this->isTenantMember($user, (int) $customerAddress->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, CustomerAddress $customerAddress): bool
    {
        return $this->isTenantMember($user, (int) $customerAddress->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }
}
