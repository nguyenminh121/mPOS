<?php

namespace App\Policies;

use App\Models\InventoryCostingConfig;
use App\Models\User;

/**
 * InventoryCostingConfigPolicy — Inventory costing method configuration.
 *
 * Changing the costing method (FIFO / LIFO / WAC) has irreversible accounting
 * implications. Only admin may modify; manager may read for operational clarity.
 *
 * No SoftDeletes on InventoryCostingConfig model.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : read-only
 *  accountant: read-only (required for cost basis understanding)
 */
class InventoryCostingConfigPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'accountant']);
    }

    public function view(User $user, InventoryCostingConfig $inventoryCostingConfig): bool
    {
        return $this->isTenantMember($user, $inventoryCostingConfig->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'accountant']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, InventoryCostingConfig $inventoryCostingConfig): bool
    {
        return $this->isTenantMember($user, $inventoryCostingConfig->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, InventoryCostingConfig $inventoryCostingConfig): bool
    {
        return $this->isTenantMember($user, $inventoryCostingConfig->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — InventoryCostingConfig does not use SoftDeletes.
}
