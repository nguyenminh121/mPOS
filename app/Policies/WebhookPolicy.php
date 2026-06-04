<?php

namespace App\Policies;

use App\Models\Webhook;
use App\Models\User;

/**
 * WebhookPolicy — Outbound webhook endpoint management.
 *
 * Webhooks push tenant data to external systems. Misconfigured webhooks
 * can leak sensitive business data or cause denial-of-service via payload floods,
 * making this admin-only.
 *
 * No SoftDeletes on Webhook model.
 *
 * Role matrix:
 *  admin : full CRUD
 */
class WebhookPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $this->isTenantMember($user, $webhook->tenant_id)
            && $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $this->isTenantMember($user, $webhook->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $this->isTenantMember($user, $webhook->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — Webhook does not use SoftDeletes.
}
