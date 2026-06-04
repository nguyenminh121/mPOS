<?php

namespace App\Policies;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * BillingPolicy — Controls access to billing / invoice records.
 *
 * - platform-admin  : full CRUD (generates invoices, records payments, etc.)
 * - admin : view their own billing history (read-only)
 */
class BillingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['platform-admin', 'admin']);
    }

    public function view(User $user, Billing $billing): bool
    {
        if ($user->tenant_id !== null
            && $user->tenant_id === $billing->tenant_id
            && $user->hasRole('admin')
        ) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function update(User $user, Billing $billing): bool
    {
        return $user->hasRole('platform-admin');
    }

    public function delete(User $user, Billing $billing): bool
    {
        return $user->hasRole('platform-admin');
    }
}
