<?php

namespace App\Policies;

use App\Models\TransferOrder;
use App\Models\User;

/**
 * TransferOrderPolicy — Inter-warehouse stock transfer management.
 *
 * Transfer flow: draft → approved → shipped → received
 * Each state transition requires appropriate authorization.
 *
 * Role matrix:
 *  admin     : full access + approve
 *  manager   : full CRUD (no forceDelete) + approve
 *  warehouse : create, read, update, ship, receive (operational role)
 *  accountant: read-only (inventory valuation across warehouses)
 *  viewer    : read-only
 *
 * Business rules:
 *  - Only admin/manager may approve (commits stock freeze on source warehouse).
 *  - Ship action available when status = 'approved'.
 *  - Receive action available when status = 'shipped'.
 *  - Delete restricted to draft transfers only.
 */
class TransferOrderPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, TransferOrder $transferOrder): bool
    {
        return $this->isTenantMember($user, $transferOrder->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /** Update draft transfers before approval. */
    public function update(User $user, TransferOrder $transferOrder): bool
    {
        return $this->isTenantMember($user, $transferOrder->tenant_id)
            && $transferOrder->status === 'draft'
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /**
     * Approve a transfer order — freezes stock on source warehouse.
     * Requires management authority.
     */
    public function approve(User $user, TransferOrder $transferOrder): bool
    {
        return $this->isTenantMember($user, $transferOrder->tenant_id)
            && $transferOrder->status === 'draft'
            && $user->hasAnyRole(['admin', 'manager']);
    }

    /** Mark as shipped — stock leaves source warehouse. */
    public function ship(User $user, TransferOrder $transferOrder): bool
    {
        return $this->isTenantMember($user, $transferOrder->tenant_id)
            && $transferOrder->status === 'approved'
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /** Confirm receipt — stock arrives at destination warehouse. */
    public function receive(User $user, TransferOrder $transferOrder): bool
    {
        return $this->isTenantMember($user, $transferOrder->tenant_id)
            && $transferOrder->status === 'shipped'
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /** Delete draft transfers only. */
    public function delete(User $user, TransferOrder $transferOrder): bool
    {
        return $this->isTenantMember($user, $transferOrder->tenant_id)
            && $transferOrder->status === 'draft'
            && $user->hasRole('admin');
    }

    public function restore(User $user, TransferOrder $transferOrder): bool
    {
        return $this->isTenantMember($user, $transferOrder->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, TransferOrder $transferOrder): bool
    {
        return $this->isTenantMember($user, $transferOrder->tenant_id)
            && $user->hasRole('admin');
    }
}
