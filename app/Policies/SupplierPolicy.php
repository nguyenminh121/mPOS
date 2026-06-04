<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

/**
 * SupplierPolicy — Supplier / vendor management.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : full CRUD (no forceDelete)
 *  warehouse : create, read, update (raises purchase orders against suppliers)
 *  accountant: read-only (debt tracking, accounts payable)
 *  viewer    : read-only
 *  cashier   : no access (suppliers are not relevant to POS operations)
 */
class SupplierPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->isTenantMember($user, $supplier->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->isTenantMember($user, $supplier->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->isTenantMember($user, $supplier->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $this->isTenantMember($user, $supplier->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return $this->isTenantMember($user, $supplier->tenant_id)
            && $user->hasRole('admin');
    }
}
