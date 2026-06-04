<?php

namespace App\Policies;

use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TenantInvitationPolicy — Controls who can manage tenant invitations.
 *
 * - platform-admin : full access across all tenants
 * - admin: manage invitations FOR their own tenant only
 */
class TenantInvitationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['platform-admin', 'admin']);
    }

    public function view(User $user, TenantInvitation $invitation): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $invitation->tenant_id
            && $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['platform-admin', 'admin']);
    }

    public function delete(User $user, TenantInvitation $invitation): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $invitation->tenant_id
            && $user->hasRole('admin');
    }

    /** Revoke a PENDING invitation before it is accepted. */
    public function revoke(User $user, TenantInvitation $invitation): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $invitation->tenant_id
            && $user->hasRole('admin')
            && $invitation->status === 'PENDING';
    }
}
