<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

/**
 * PromotionPolicy — Discount and promotional campaign management.
 *
 * Cashiers and viewers need read access to see which promotions apply during a sale.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : full CRUD (no forceDelete)
 *  cashier   : read-only (promotion lookup at POS)
 *  accountant: read-only (promo cost impact in reporting)
 *  viewer    : read-only
 *  warehouse : no access (promotions are not a warehouse concern)
 */
class PromotionPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $this->isTenantMember($user, $promotion->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $this->isTenantMember($user, $promotion->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $this->isTenantMember($user, $promotion->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function restore(User $user, Promotion $promotion): bool
    {
        return $this->isTenantMember($user, $promotion->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Promotion $promotion): bool
    {
        return $this->isTenantMember($user, $promotion->tenant_id)
            && $user->hasRole('admin');
    }
}
