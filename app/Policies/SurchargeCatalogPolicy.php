<?php

namespace App\Policies;

use App\Models\SurchargeCatalog;
use App\Models\User;

/**
 * SurchargeCatalogPolicy — Surcharge / fee catalog management.
 *
 * Cashiers need read access to apply surcharges at POS.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : create, read, update, delete (no forceDelete)
 *  cashier   : read-only (surcharge lookup at POS)
 *  accountant: read-only (surcharge revenue in reporting)
 *  viewer    : read-only
 */
class SurchargeCatalogPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function view(User $user, SurchargeCatalog $surchargeCatalog): bool
    {
        return $this->isTenantMember($user, $surchargeCatalog->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, SurchargeCatalog $surchargeCatalog): bool
    {
        return $this->isTenantMember($user, $surchargeCatalog->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, SurchargeCatalog $surchargeCatalog): bool
    {
        return $this->isTenantMember($user, $surchargeCatalog->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function restore(User $user, SurchargeCatalog $surchargeCatalog): bool
    {
        return $this->isTenantMember($user, $surchargeCatalog->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, SurchargeCatalog $surchargeCatalog): bool
    {
        return $this->isTenantMember($user, $surchargeCatalog->tenant_id)
            && $user->hasRole('admin');
    }
}
