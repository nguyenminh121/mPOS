<?php

namespace App\Policies;

use App\Models\TaxConfig;
use App\Models\User;

/**
 * TaxConfigPolicy — Tax rate and tax code configuration.
 *
 * No SoftDeletes on TaxConfig model.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : read-only (needs to understand tax treatment on invoices)
 *  accountant: read-only (tax reporting and filing)
 *  cashier   : read-only (GST/VAT applied at POS)
 */
class TaxConfigPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'cashier']);
    }

    public function view(User $user, TaxConfig $taxConfig): bool
    {
        return $this->isTenantMember($user, $taxConfig->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'accountant', 'cashier']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, TaxConfig $taxConfig): bool
    {
        return $this->isTenantMember($user, $taxConfig->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, TaxConfig $taxConfig): bool
    {
        return $this->isTenantMember($user, $taxConfig->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — TaxConfig does not use SoftDeletes.
}
