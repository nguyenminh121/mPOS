<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Models\Role;

/**
 * RolePolicy — Controls who can manage Spatie permission roles.
 *
 * - platform-admin  : full CRUD on all roles
 *   Exception: cannot modify or delete core protected roles
 *   ('platform-admin' itself) to prevent privilege escalation.
 * - Gate::before() handles the platform-admin bypass.
 *   These methods are authoritative for non-platform-admin users.
 */
class RolePolicy
{
    use HandlesAuthorization;

    /** Roles that must never be modified or deleted via the UI. */
    protected const PROTECTED_ROLES = ['platform-admin'];

    /** Roles that may not be deleted (but CAN be updated — e.g., adding permissions). */
    protected const UNDELETABLE_ROLES = ['platform-admin', 'admin'];

    /** Tenant admins may only assign tenant-scoped operational roles. */
    protected const TENANT_ASSIGNABLE_ROLES = [
        'admin',
        'manager',
        'cashier',
        'warehouse',
        'accountant',
        'viewer',
    ];

    public function viewAny(User $user): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('platform-admin');
    }

    /** Cannot update protected core roles. */
    public function update(User $user, Role $role): bool
    {
        return $user->hasRole('platform-admin')
            && ! in_array($role->name, self::PROTECTED_ROLES, true);
    }

    /** Cannot delete roles that are in use or are undeletable system roles. */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasRole('platform-admin')
            && ! in_array($role->name, self::UNDELETABLE_ROLES, true)
            && $role->users()->count() === 0;
    }

    /** Assign a role to a user. */
    public function assign(User $user, Role $role): bool
    {
        // platform-admin role assignment is handled by Gate::before platform bypass.
        // Tenant admins can only assign tenant-scoped roles.
        if (
            $user->tenant_id !== null
            && $user->hasRole('admin')
            && in_array($role->name, self::TENANT_ASSIGNABLE_ROLES, true)
        ) {
            return true;
        }

        return false;
    }
}
