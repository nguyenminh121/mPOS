<?php

namespace App\Policies;

use App\Models\ReturnOrder;
use App\Models\User;

/**
 * ReturnOrderPolicy — Customer return / refund management.
 *
 * Role matrix:
 *  admin     : full access + approve
 *  manager   : full CRUD (no forceDelete) + approve
 *  cashier   : create + read (initiating returns at POS counter)
 *  warehouse : read-only (restocking awareness after approval)
 *  accountant: read-only (refund/credit tracking)
 *  viewer    : read-only
 *
 * Business rules:
 *  - Returns require approval before processing (approved_by field).
 *  - Only pending/draft returns may be edited or deleted.
 *  - Approved/completed returns are immutable.
 */
class ReturnOrderPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->isTenantMember($user, $returnOrder->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function createFromPos(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function update(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->isTenantMember($user, $returnOrder->tenant_id)
            && in_array($returnOrder->status, ['pending', 'draft'], true)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    /** Approve a pending return — triggers restocking and refund flow. */
    public function approve(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->isTenantMember($user, $returnOrder->tenant_id)
            && $returnOrder->status === 'pending'
            && $user->hasAnyRole(['admin', 'manager']);
    }

    /** Delete only allowed while still in pending / draft state. */
    public function delete(User $user, ReturnOrder $returnOrder): bool
    {
        return $this->isTenantMember($user, $returnOrder->tenant_id)
            && in_array($returnOrder->status, ['pending', 'draft'], true)
            && $user->hasRole('admin');
    }

    // restore() and forceDelete() omitted — ReturnOrder does not use SoftDeletes.
}
