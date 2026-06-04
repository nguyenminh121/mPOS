<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * CustomerPolicy — Customer / CRM record management.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : full CRUD (no forceDelete)
 *  cashier   : create + read (walk-in / new customer at POS checkout)
 *  accountant: read-only (debt/balance reconciliation)
 *  viewer    : read-only
 *  warehouse : no access (not relevant to warehouse operations)
 */
class CustomerPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->isTenantMember($user, $customer->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->isTenantMember($user, $customer->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->isTenantMember($user, $customer->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $this->isTenantMember($user, $customer->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $this->isTenantMember($user, $customer->tenant_id)
            && $user->hasRole('admin');
    }
}
