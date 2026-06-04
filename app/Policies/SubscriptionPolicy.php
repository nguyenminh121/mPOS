<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * SubscriptionPolicy — Controls plan / subscription management.
 *
 * - platform-admin  : full CRUD (assigns plans to tenants, cancels, etc.)
 * - admin : view their own tenant's subscription (read-only)
 */
class SubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['platform-admin', 'admin']);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        // admin may view their own subscription
        if ($user->tenant_id !== null
            && $user->tenant_id === $subscription->tenant_id
            && $user->hasRole('admin')
        ) {
            return true;
        }

        return false;
    }

    /** Only platform-admin creates subscription records (platform-side action). */
    public function create(User $user): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('platform-admin');
    }
}
