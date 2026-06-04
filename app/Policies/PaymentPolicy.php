<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * PaymentPolicy — Payment transaction management.
 *
 * Role matrix:
 *  admin     : full access
 *  manager   : read, create, update
 *  cashier   : read + create (collecting payments at POS)
 *  accountant: read + update (reconciliation corrections)
 *  viewer    : read-only
 *
 * Business rule: payment records are financial audit data.
 * Delete is admin-only to preserve ledger integrity.
 */
class PaymentPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->isTenantMember($user, $payment->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $this->isTenantMember($user, $payment->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'accountant']);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->isTenantMember($user, $payment->tenant_id)
            && $user->hasRole('admin');
    }
}
