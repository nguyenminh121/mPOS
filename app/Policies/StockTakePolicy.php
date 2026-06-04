<?php

namespace App\Policies;

use App\Models\StockTake;
use App\Models\User;

/**
 * StockTakePolicy — Physical stock count / inventory audit management.
 *
 * Role matrix:
 *  admin     : full access
 *  manager   : full CRUD + complete (no forceDelete)
 *  warehouse : create, read, update, complete (primary operator)
 *  accountant: read-only (for variance / shrinkage reporting)
 *  viewer    : read-only
 *
 * Business rules:
 *  - Completed stock takes are immutable (status = 'completed').
 *  - Only draft stock takes may be deleted.
 *  - Completing a stock take triggers StockMovement records for all variances.
 */
class StockTakePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, StockTake $stockTake): bool
    {
        return $this->isTenantMember($user, $stockTake->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /** Update allowed only while the stock take is not yet completed. */
    public function update(User $user, StockTake $stockTake): bool
    {
        return $this->isTenantMember($user, $stockTake->tenant_id)
            && in_array(strtoupper((string) $stockTake->status), ['DRAFT', 'IN_PROGRESS'], true)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /**
     * Complete a stock take — finalises variances and posts StockMovements.
     * Requires status = 'in_progress'.
     */
    public function complete(User $user, StockTake $stockTake): bool
    {
        return $this->isTenantMember($user, $stockTake->tenant_id)
            && strtoupper((string) $stockTake->status) === 'IN_PROGRESS'
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /** Undo a completed stock take for correction and recount. */
    public function undo(User $user, StockTake $stockTake): bool
    {
        return $this->isTenantMember($user, $stockTake->tenant_id)
            && strtoupper((string) $stockTake->status) === 'COMPLETED'
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /** Delete only draft stock takes (nothing has been counted yet). */
    public function delete(User $user, StockTake $stockTake): bool
    {
        return $this->isTenantMember($user, $stockTake->tenant_id)
            && strtoupper((string) $stockTake->status) !== 'COMPLETED'
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    // restore() omitted — StockTake does not use SoftDeletes.
}
