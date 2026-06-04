<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSalesSummary extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'summary_date',
        'product_variant_id',
        'quantity',
        'revenue',
        'cost',
        'profit',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'revenue' => 'decimal:2',
            'cost' => 'decimal:2',
            'profit' => 'decimal:2',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
