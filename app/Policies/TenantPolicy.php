<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TenantPolicy — Central platform management.
 *
 * Only platform-admin may manage Tenant records.
 * Gate::before() in AppServiceProvider short-circuits all checks
 * for platform-admin, so these methods also serve as safe fallbacks
 * if policies are ever called outside the Gate.
 */
class TenantPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('platform-admin');
    }

    /** Soft-delete a tenant. Only allowed when not currently active. */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('platform-admin')
            && $tenant->status !== 'active';
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('platform-admin');
    }

    /** Suspend an active tenant (blocks login for all tenant users). */
    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('platform-admin')
            && $tenant->status === 'active';
    }

    /** Reinstate a suspended or expired tenant. */
    public function reinstate(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('platform-admin')
            && in_array($tenant->status, ['suspended', 'expired'], true);
    }
}
