<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * PurchaseOrderPolicy — Purchase order (supplier ordering) management.
 *
 * Role matrix:
 *  admin     : full access + approve
 *  manager   : full CRUD (no forceDelete) + approve
 *  warehouse : create, read, update (raises POs for stock replenishment)
 *  accountant: read-only (cost tracking, accounts payable)
 *  viewer    : read-only
 *
 * Business rules:
 *  - Only admin/manager may approve a PO (commits spend authority).
 *  - Draft POs may be updated or deleted before approval.
 *  - Approved POs are locked for edit; cancel + reissue is the correct flow.
 */
class PurchaseOrderPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isTenantMember($user, $purchaseOrder->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /** Update allowed while still in draft or pending_approval status. */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isTenantMember($user, $purchaseOrder->tenant_id)
            && in_array($purchaseOrder->status, ['draft', 'pending_approval'], true)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /**
     * Approve a purchase order — commits spend authority and enables import receipt creation.
     * Requires spend approval authority (admin or manager).
     */
    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isTenantMember($user, $purchaseOrder->tenant_id)
            && $purchaseOrder->status === 'pending_approval'
            && $user->hasAnyRole(['admin', 'manager']);
    }

    /** Delete restricted to draft POs only. */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isTenantMember($user, $purchaseOrder->tenant_id)
            && $purchaseOrder->status === 'draft'
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function restore(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isTenantMember($user, $purchaseOrder->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isTenantMember($user, $purchaseOrder->tenant_id)
            && $user->hasRole('admin');
    }
}
