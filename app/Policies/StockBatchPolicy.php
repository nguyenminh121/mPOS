<?php

namespace App\Policies;

use App\Models\StockBatch;
use App\Models\User;

/**
 * StockBatchPolicy — Stock batch / lot tracking (read-only ledger).
 *
 * StockBatch has no direct tenant_id — it is scoped via warehouse_id.
 * Tenant isolation is enforced by the per-tenant DB connection (stancl/tenancy).
 * Batches are created by the system when goods are received; they are never
 * created or deleted directly by users.
 *
 * No SoftDeletes on StockBatch model.
 *
 * Role matrix:
 *  admin     : read-only (full visibility for auditing)
 *  warehouse : read-only (primary operator — pick/put away by batch)
 *  accountant: read-only (FIFO/LIFO cost layer tracking)
 *  manager   : read-only (oversight)
 */
class StockBatchPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'warehouse', 'accountant', 'manager']);
    }

    public function view(User $user, StockBatch $stockBatch): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'warehouse', 'accountant', 'manager']);
    }

    // Batches are system-generated — no create, update, delete, restore, or forceDelete.
}
