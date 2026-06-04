<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * BaseTenantPolicy — Shared foundation for all tenant-scoped policies.
 *
 * Provides isTenantMember() which enforces TWO invariants:
 *   1. The acting user is a tenant user (tenant_id !== null).
 *      Central/platform users (tenant_id = null) always return false.
 *   2. The user belongs to the SAME tenant as the resource being accessed.
 *
 * Gate::before() in AppServiceProvider already short-circuits for
 * `platform-admin`, so platform-level access never reaches these policies.
 *
 * stancl/tenancy v3 (HasDatabase) switches the DB connection per-subdomain,
 * providing an additional infrastructure-level tenant isolation layer.
 * The isTenantMember() check is the application-layer enforcement on top.
 */
abstract class BaseTenantPolicy
{
    use HandlesAuthorization;

    /**
     * Confirm the acting user belongs to the specified tenant.
     *
     * @param  User  $user             The authenticated user
     * @param  int   $resourceTenantId The tenant_id of the model being authorized
     */
    protected function isTenantMember(User $user, int $resourceTenantId): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $resourceTenantId;
    }
}
