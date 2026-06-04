<?php

namespace App\Policies;

use App\Models\Uom;
use App\Models\User;

/**
 * UomPolicy — Unit of Measure management.
 *
 * UoMs are shared reference data used across products.
 * Only admin may delete; manager may create/update.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : create, read, update (no delete)
 *  cashier   : read-only (unit displayed at POS)
 *  warehouse : read-only (unit context for stock quantities)
 *  accountant: read-only
 *  viewer    : read-only
 */
class UomPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, Uom $uom): bool
    {
        return $this->isTenantMember($user, $uom->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, Uom $uom): bool
    {
        return $this->isTenantMember($user, $uom->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, Uom $uom): bool
    {
        return $this->isTenantMember($user, $uom->tenant_id)
            && $user->hasRole('admin');
    }

    public function restore(User $user, Uom $uom): bool
    {
        return $this->isTenantMember($user, $uom->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Uom $uom): bool
    {
        return $this->isTenantMember($user, $uom->tenant_id)
            && $user->hasRole('admin');
    }
}
