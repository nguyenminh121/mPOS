<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InventoryCostingConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'method',
        'pricing_strategy',
        'default_margin_percent',
    ];

    protected function casts(): array
    {
        return [
            'default_margin_percent' => 'decimal:2',
        ];
    }
}
