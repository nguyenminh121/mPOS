<?php

namespace App\Policies;

use App\Models\CustomerGroup;
use App\Models\User;

/**
 * CustomerGroupPolicy — Customer segmentation group management.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : create, read, update, delete (no forceDelete)
 *  cashier   : read-only (group assignment when creating a customer at POS)
 *  accountant: read-only (group-level revenue reporting)
 *  viewer    : read-only
 */
class CustomerGroupPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function view(User $user, CustomerGroup $customerGroup): bool
    {
        return $this->isTenantMember($user, $customerGroup->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, CustomerGroup $customerGroup): bool
    {
        return $this->isTenantMember($user, $customerGroup->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, CustomerGroup $customerGroup): bool
    {
        return $this->isTenantMember($user, $customerGroup->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function restore(User $user, CustomerGroup $customerGroup): bool
    {
        return $this->isTenantMember($user, $customerGroup->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, CustomerGroup $customerGroup): bool
    {
        return $this->isTenantMember($user, $customerGroup->tenant_id)
            && $user->hasRole('admin');
    }
}
