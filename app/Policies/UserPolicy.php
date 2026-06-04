<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * UserPolicy — Manages who can administer user accounts.
 *
 * Hierarchy:
 *  - platform-admin  : full access to ALL users across ALL tenants (bypassed by Gate::before)
 *  - admin : CRUD users within their OWN tenant only
 *  - manager : read-only user list within their own tenant
 *  - Any user     : may update / view their own profile
 *
 * Central users (tenant_id = null) can only be managed by platform-admin.
 */
class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['platform-admin', 'admin', 'manager']);
    }

    public function view(User $user, User $target): bool
    {
        // own profile
        if ($user->id === $target->id) {
            return true;
        }

        return $user->tenant_id !== null
            && $user->tenant_id === $target->tenant_id
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['platform-admin', 'admin']);
    }

    public function update(User $user, User $target): bool
    {
        // own profile
        if ($user->id === $target->id) {
            return true;
        }

        return $user->tenant_id !== null
            && $user->tenant_id === $target->tenant_id
            && $user->hasRole('admin');
    }

    /** Users cannot delete themselves. */
    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        return $user->tenant_id !== null
            && $user->tenant_id === $target->tenant_id
            && $user->hasRole('admin');
    }

    public function restore(User $user, User $target): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $target->tenant_id
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, User $target): bool
    {
        // Only platform-admin may permanently erase a user record
        return $user->hasRole('platform-admin');
    }

    /** Assign or revoke a role from a user. */
    public function assignRole(User $user, User $target): bool
    {
        if ($user->tenant_id !== null
            && $user->tenant_id === $target->tenant_id
            && $user->hasRole('admin')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Impersonate: platform-admin may log in as any non-platform-admin user
     * for support purposes.
     */
    public function impersonate(User $user, User $target): bool
    {
        return $user->hasRole('platform-admin')
            && ! $target->hasRole('platform-admin');
    }
}
