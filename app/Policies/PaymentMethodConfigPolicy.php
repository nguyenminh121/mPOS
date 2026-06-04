<?php

namespace App\Policies;

use App\Models\PaymentMethodConfig;
use App\Models\User;

/**
 * PaymentMethodConfigPolicy — Payment method (cash, card, etc.) configuration.
 *
 * No SoftDeletes on PaymentMethodConfig model.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : read-only (oversight of accepted tender types)
 *  cashier   : read-only (selects payment method during checkout)
 */
class PaymentMethodConfigPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function view(User $user, PaymentMethodConfig $paymentMethodConfig): bool
    {
        return $this->isTenantMember($user, $paymentMethodConfig->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, PaymentMethodConfig $paymentMethodConfig): bool
    {
        return $this->isTenantMember($user, $paymentMethodConfig->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, PaymentMethodConfig $paymentMethodConfig): bool
    {
        return $this->isTenantMember($user, $paymentMethodConfig->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — PaymentMethodConfig does not use SoftDeletes.
}
