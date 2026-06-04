<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

/**
 * CreditNotePolicy — Credit note management.
 *
 * Credit notes are typically system-generated from approved return orders
 * or issued manually by admin/manager to correct invoices.
 * No SoftDeletes on CreditNote model.
 *
 * Role matrix:
 *  admin     : read + create (manual issuance) + update/delete (corrections only)
 *  manager   : read + create (manual issuance)
 *  accountant: read-only (accounts receivable reconciliation)
 *  cashier   : read-only (customer credit at POS)
 *  viewer    : read-only
 */
class CreditNotePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'cashier', 'viewer']);
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $this->isTenantMember($user, $creditNote->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'cashier', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return $this->isTenantMember($user, $creditNote->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — CreditNote does not use SoftDeletes.
    public function delete(User $user, CreditNote $creditNote): bool
    {
        return $this->isTenantMember($user, $creditNote->tenant_id)
            && $user->hasRole('admin');
    }
}
