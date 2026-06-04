<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

/**
 * ApiKeyPolicy — Tenant API key management.
 *
 * API keys grant programmatic access to tenant data. Key exposure allows
 * full data access, making lifecycle management admin-only.
 *
 * No SoftDeletes on ApiKey model.
 *
 * Role matrix:
 *  admin : full CRUD (create, rotate, revoke)
 */
class ApiKeyPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function view(User $user, ApiKey $apiKey): bool
    {
        return $this->isTenantMember($user, $apiKey->tenant_id)
            && $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, ApiKey $apiKey): bool
    {
        return $this->isTenantMember($user, $apiKey->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $this->isTenantMember($user, $apiKey->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — ApiKey does not use SoftDeletes.
}
