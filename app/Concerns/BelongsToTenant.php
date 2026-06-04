<?php

namespace App\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attach to any model that carries a tenant_id foreign key.
 * Provides the tenant() relationship and a global scope to
 * automatically filter queries by the current tenant when
 * running inside a tenant context.
 */
trait BelongsToTenant
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (! $model->tenant_id && function_exists('tenant') && tenant()) {
                $model->tenant_id = tenant()->getTenantKey();
            }
        });
    }
}
