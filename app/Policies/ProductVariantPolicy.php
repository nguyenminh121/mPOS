<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

/**
 * ProductVariantPolicy — Product variant (SKU) management.
 *
 * ProductVariant has no direct tenant_id — it is scoped via product_id → Product.
 * Tenant isolation is enforced at the infrastructure level by stancl/tenancy
 * per-tenant DB connection. The $user->tenant_id !== null guard ensures
 * central platform users cannot access tenant variant data.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : create, read, update (no delete)
 *  cashier   : read-only (variant selection at POS)
 *  warehouse : read-only (stock operations by variant)
 *  accountant: read-only (cost price / costing data)
 *  viewer    : read-only
 */
class ProductVariantPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, ProductVariant $variant): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function restore(User $user, ProductVariant $variant): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, ProductVariant $variant): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }
}
