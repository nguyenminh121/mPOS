<?php

namespace App\Policies;

use App\Models\WarehouseLocation;
use App\Models\User;

/**
 * WarehouseLocationPolicy — Physical bin/rack/shelf location management.
 *
 * WarehouseLocation has no direct tenant_id — it is scoped via warehouse_id.
 * Tenant isolation is enforced by the per-tenant DB connection (stancl/tenancy).
 * The $user->tenant_id !== null guard blocks central platform users.
 *
 * No SoftDeletes on WarehouseLocation model.
 *
 * Role matrix:
 *  admin     : full CRUD (warehouse layout management)
 *  warehouse : create, read, update (operational location management)
 *  manager   : read-only (oversight)
 *  accountant: read-only (stock valuation by location context)
 *  viewer    : read-only
 */
class WarehouseLocationPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'warehouse', 'manager', 'accountant', 'viewer']);
    }

    public function view(User $user, WarehouseLocation $warehouseLocation): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'warehouse', 'manager', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'warehouse']);
    }

    public function update(User $user, WarehouseLocation $warehouseLocation): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'warehouse']);
    }

    // Hard delete: admin only — location removal affects stock position records.
    public function delete(User $user, WarehouseLocation $warehouseLocation): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — WarehouseLocation does not use SoftDeletes.
}
