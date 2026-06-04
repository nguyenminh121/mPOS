<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

/**
 * WarehousePolicy — Warehouse configuration management.
 *
 * Warehouses are infrastructure-level config — only admin may modify.
 *
 * Role matrix:
 *  admin     : full CRUD + setDefault
 *  manager   : read-only (sees which warehouses exist for order assignment)
 *  warehouse : read-only (staff know which warehouse they operate in)
 *  accountant: read-only (warehouse context for cost/stock reports)
 *  viewer    : read-only
 *  cashier   : no access (POS operates on a default warehouse, set by admin)
 *
 * Business rule: cannot delete an active warehouse.
 * Set is_active = false first, then delete.
 */
class WarehousePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->isTenantMember($user, $warehouse->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->isTenantMember($user, $warehouse->tenant_id)
            && $user->hasRole('admin');
    }

    /** Delete only allowed when warehouse is deactivated (is_active = false). */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $this->isTenantMember($user, $warehouse->tenant_id)
            && ! $warehouse->is_active
            && $user->hasRole('admin');
    }

    public function restore(User $user, Warehouse $warehouse): bool
    {
        return $this->isTenantMember($user, $warehouse->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Warehouse $warehouse): bool
    {
        return $this->isTenantMember($user, $warehouse->tenant_id)
            && $user->hasRole('admin');
    }

    /** Mark a warehouse as the default for new orders and POS sessions. */
    public function setDefault(User $user, Warehouse $warehouse): bool
    {
        return $this->isTenantMember($user, $warehouse->tenant_id)
            && $user->hasRole('admin');
    }
}
