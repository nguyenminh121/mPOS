<?php

namespace App\Policies;

use App\Models\PriceList;
use App\Models\User;

/**
 * PriceListPolicy — Customer price list management.
 *
 * Price lists map products to per-customer-group prices.
 * Cashiers need read access to apply the correct price at POS.
 * Accountants need read access for margin and revenue analysis.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : create, read, update, delete (no forceDelete)
 *  cashier   : read-only (price lookup during order entry)
 *  accountant: read-only (pricing analysis and reporting)
 *  viewer    : read-only
 */
class PriceListPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $this->isTenantMember($user, $priceList->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $this->isTenantMember($user, $priceList->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $this->isTenantMember($user, $priceList->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function restore(User $user, PriceList $priceList): bool
    {
        return $this->isTenantMember($user, $priceList->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, PriceList $priceList): bool
    {
        return $this->isTenantMember($user, $priceList->tenant_id)
            && $user->hasRole('admin');
    }
}
