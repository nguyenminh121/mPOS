<?php

namespace App\Policies;

use App\Models\BusinessExpense;
use App\Models\User;

/**
 * BusinessExpensePolicy — Operating expense tracking and approval.
 *
 * No SoftDeletes on BusinessExpense model.
 *
 * Workflow: draft → pending_approval → approved / rejected
 *
 * Role matrix:
 *  admin     : full lifecycle (CRUD + approve/reject)
 *  manager   : create, read, update (pending only), approve/reject
 *  accountant: read-only + approve/reject
 *  viewer    : read-only
 *
 * Custom methods:
 *  approve : admin/manager/accountant may approve a pending expense
 */
class BusinessExpensePolicy extends BaseTenantPolicy
{
    private const EDITABLE_STATUSES = ['PENDING'];

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'viewer']);
    }

    public function view(User $user, BusinessExpense $expense): bool
    {
        return $this->isTenantMember($user, $expense->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, BusinessExpense $expense): bool
    {
        if (! $this->isTenantMember($user, $expense->tenant_id)) {
            return false;
        }

        if (! in_array((string) $expense->payment_status, self::EDITABLE_STATUSES, true)) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'manager']);
    }

    /** Approve (or reject) a pending expense. */
    public function approve(User $user, BusinessExpense $expense): bool
    {
        if (! $this->isTenantMember($user, $expense->tenant_id)) {
            return false;
        }

        if ((string) $expense->payment_status !== 'PENDING') {
            return false;
        }

        return $user->hasAnyRole(['admin', 'manager', 'accountant']);
    }

    // Hard delete is admin-only and only valid when still in draft.
    public function delete(User $user, BusinessExpense $expense): bool
    {
        return $this->isTenantMember($user, $expense->tenant_id)
                && (string) $expense->payment_status === 'PENDING'
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — BusinessExpense does not use SoftDeletes.
}
