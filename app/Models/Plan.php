<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'tier',
        'db_mode',
        'billing_cycle',
        'price',
        'currency',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function moduleAccess(): HasMany
    {
        return $this->hasMany(PlanModuleAccess::class);
    }
}
