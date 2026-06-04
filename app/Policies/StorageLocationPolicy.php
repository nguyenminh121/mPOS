<?php

namespace App\Policies;

use App\Models\StorageLocation;
use App\Models\User;

/**
 * StorageLocationPolicy — Storage location management within a warehouse.
 *
 * No SoftDeletes on StorageLocation model.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  warehouse : create, read, update (operational management)
 *  manager   : read-only
 *  accountant: read-only (stock valuation context)
 *  viewer    : read-only
 */
class StorageLocationPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'warehouse', 'manager', 'accountant', 'viewer']);
    }

    public function view(User $user, StorageLocation $storageLocation): bool
    {
        return $this->isTenantMember($user, $storageLocation->tenant_id)
            && $user->hasAnyRole(['admin', 'warehouse', 'manager', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'warehouse']);
    }

    public function update(User $user, StorageLocation $storageLocation): bool
    {
        return $this->isTenantMember($user, $storageLocation->tenant_id)
            && $user->hasAnyRole(['admin', 'warehouse']);
    }

    // Hard delete: admin only.
    public function delete(User $user, StorageLocation $storageLocation): bool
    {
        return $this->isTenantMember($user, $storageLocation->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — StorageLocation does not use SoftDeletes.
}
