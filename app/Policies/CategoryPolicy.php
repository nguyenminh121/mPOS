<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * CategoryPolicy — Product category tree management.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : create, read, update (no delete)
 *  cashier   : read-only (category context at POS)
 *  warehouse : read-only (category context for stock ops)
 *  accountant: read-only (category-level cost/sales reporting)
 *  viewer    : read-only
 */
class CategoryPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->isTenantMember($user, $category->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->isTenantMember($user, $category->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->isTenantMember($user, $category->tenant_id)
            && $user->hasRole('admin');
    }

    public function restore(User $user, Category $category): bool
    {
        return $this->isTenantMember($user, $category->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $this->isTenantMember($user, $category->tenant_id)
            && $user->hasRole('admin');
    }
}
