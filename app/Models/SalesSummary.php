<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SalesSummary extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'summary_date',
        'total_orders',
        'total_items',
        'gross_revenue',
        'discount_total',
        'net_revenue',
        'cost_total',
        'profit',
        'retail_revenue',
        'wholesale_revenue',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'gross_revenue' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'net_revenue' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'profit' => 'decimal:2',
            'retail_revenue' => 'decimal:2',
            'wholesale_revenue' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }
}
