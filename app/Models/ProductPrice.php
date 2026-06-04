<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_variant_id',
        'cost_price',
        'retail_price',
        'wholesale_price',
        'uom_id',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
