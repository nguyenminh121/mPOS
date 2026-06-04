<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * OrderPolicy — Sales order management.
 *
 * Role matrix:
 *  admin     : full CRUD + lock/unlock/cancel
 *  manager   : CRUD (no forceDelete) + lock/cancel
 *  cashier   : create + read + update OWN draft orders only
 *  accountant: read-only (payment reconciliation)
 *  viewer    : read-only
 *  warehouse : read-only (stock deduction awareness)
 *
 * Business rules:
 *  - Locked orders cannot be updated or deleted by anyone.
 *    Admin must explicitly unlock before editing.
 *  - Only draft / cancelled orders may be soft-deleted.
 *  - Cashiers may only edit their own orders while status is draft/pending.
 */
class OrderPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->isTenantMember($user, $order->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function createPreorder(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function update(User $user, Order $order): bool
    {
        if (! $this->isTenantMember($user, $order->tenant_id) || $order->is_locked) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        // Cashiers may only edit their own orders while still in draft/pending
        if ($user->hasRole('cashier')) {
            $status = strtoupper((string) $order->status);

            return $order->user_id === $user->id
                && in_array($status, ['DRAFT', 'PREORDER'], true);
        }

        return false;
    }

    /** Soft-delete: not allowed for completed/cancelled orders. Locked orders are protected. */
    public function delete(User $user, Order $order): bool
    {
        return $this->isTenantMember($user, $order->tenant_id)
            && ! $order->is_locked
            && ! in_array(strtoupper((string) $order->status), ['COMPLETED', 'CANCELLED'], true)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function restore(User $user, Order $order): bool
    {
        return $this->isTenantMember($user, $order->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $this->isTenantMember($user, $order->tenant_id)
            && $user->hasRole('admin');
    }

    /** Lock an order to prevent further edits (e.g., after invoicing). */
    public function lock(User $user, Order $order): bool
    {
        return $this->isTenantMember($user, $order->tenant_id)
            && ! $order->is_locked
            && $user->hasAnyRole(['admin', 'manager']);
    }

    /** Unlock a locked order — admin only; requires deliberate intent. */
    public function unlock(User $user, Order $order): bool
    {
        return $this->isTenantMember($user, $order->tenant_id)
            && $order->is_locked
            && $user->hasRole('admin');
    }

    /** Cancel an order that has not yet reached a terminal state. */
    public function cancel(User $user, Order $order): bool
    {
        return $this->isTenantMember($user, $order->tenant_id)
            && ! $order->is_locked
            && ! in_array($order->status, ['completed', 'cancelled'], true)
            && $user->hasAnyRole(['admin', 'manager']);
    }
}
