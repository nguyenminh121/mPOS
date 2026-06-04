<?php

namespace App\Policies;

use App\Models\AccountPayable;
use App\Models\User;

/**
 * AccountPayablePolicy — Accounts payable ledger entries.
 *
 * AP entries are system-generated from purchase orders and supplier payments.
 * They are an immutable financial ledger — only read access is granted.
 * No SoftDeletes and no write methods.
 *
 * Role matrix:
 *  admin     : read-only
 *  manager   : read-only (cash-flow oversight)
 *  accountant: read-only (primary user for AP reconciliation)
 *  warehouse : read-only (vendor debt context for receiving decisions)
 *  viewer    : read-only
 */
class AccountPayablePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'warehouse', 'viewer']);
    }

    public function view(User $user, AccountPayable $accountPayable): bool
    {
        return $this->isTenantMember($user, $accountPayable->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'warehouse', 'viewer']);
    }

    // Ledger is immutable — no create, update, delete, restore, or forceDelete.
}
