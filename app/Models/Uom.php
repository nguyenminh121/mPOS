<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Uom extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'symbol',
        'base_uom_id',
        'conversion_factor',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_uom_id');
    }

    public function derivedUoms(): HasMany
    {
        return $this->hasMany(self::class, 'base_uom_id');
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }
}
