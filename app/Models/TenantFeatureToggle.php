<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeatureToggle extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'feature_id',
        'is_enabled',
        'disabled_at',
        'disabled_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public function disabledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }
}
