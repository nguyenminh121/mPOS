<?php

namespace App\Policies;

use App\Models\AccountReceivable;
use App\Models\User;

/**
 * AccountReceivablePolicy — Accounts receivable ledger entries.
 *
 * AR entries are system-generated from invoices and payments.
 * They are an immutable financial ledger — only read access is granted.
 * No SoftDeletes and no write methods.
 *
 * Role matrix:
 *  admin     : read-only
 *  manager   : read-only (cash-flow oversight)
 *  accountant: read-only (primary user for AR reconciliation)
 *  viewer    : read-only
 */
class AccountReceivablePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'viewer']);
    }

    public function view(User $user, AccountReceivable $accountReceivable): bool
    {
        return $this->isTenantMember($user, $accountReceivable->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'viewer']);
    }

    // Ledger is immutable — no create, update, delete, restore, or forceDelete.
}
