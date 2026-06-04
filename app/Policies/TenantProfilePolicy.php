<?php

namespace App\Policies;

use App\Models\TenantProfile;
use App\Models\User;

/**
 * TenantProfilePolicy — Tenant company profile and branding settings.
 *
 * The profile contains the business name, logo, address, and branding colours
 * displayed across all tenant-facing documents and UI.
 * All tenant roles can read the profile for display purposes; only admin may edit.
 *
 * No SoftDeletes on TenantProfile model.
 *
 * Role matrix:
 *  admin     : full CRUD (company info + branding)
 *  manager   : read-only
 *  cashier   : read-only (logo/name displayed at POS)
 *  warehouse : read-only
 *  accountant: read-only
 *  viewer    : read-only
 */
class TenantProfilePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, TenantProfile $tenantProfile): bool
    {
        return $this->isTenantMember($user, $tenantProfile->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, TenantProfile $tenantProfile): bool
    {
        return $this->isTenantMember($user, $tenantProfile->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, TenantProfile $tenantProfile): bool
    {
        return $this->isTenantMember($user, $tenantProfile->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — TenantProfile does not use SoftDeletes.
}
