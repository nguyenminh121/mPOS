<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * ProductPolicy — Catalog / product master data management.
 *
 * Role matrix:
 *  admin     : full CRUD including hard delete
 *  manager   : create, read, update (no delete)
 *  cashier   : read-only (needs product list for POS checkout)
 *  warehouse : read-only (needs product list for stock operations)
 *  accountant: read-only (product data for cost reporting)
 *  viewer    : read-only
 */
class ProductPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->isTenantMember($user, $product->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->isTenantMember($user, $product->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->isTenantMember($user, $product->tenant_id)
            && $user->hasRole('admin');
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->isTenantMember($user, $product->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $this->isTenantMember($user, $product->tenant_id)
            && $user->hasRole('admin');
    }
}
