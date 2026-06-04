<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;

/**
 * AttributePolicy — Product attribute definition management (colour, size, etc.).
 *
 * No SoftDeletes on Attribute model.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : create, read, update (no delete)
 *  cashier   : read-only (attribute values shown at POS for variant selection)
 *  warehouse : read-only
 *  accountant: read-only
 *  viewer    : read-only
 */
class AttributePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $this->isTenantMember($user, $attribute->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $this->isTenantMember($user, $attribute->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    // No restore/forceDelete — Attribute does not use SoftDeletes.
    public function delete(User $user, Attribute $attribute): bool
    {
        return $this->isTenantMember($user, $attribute->tenant_id)
            && $user->hasRole('admin');
    }
}
