<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantAttribute extends Model
{
    protected $fillable = [
        'product_variant_id',
        'attribute_id',
        'description',
        'value_number',
        'value_text',
        'value_date',
        'value_bool',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_date' => 'date',
            'value_bool' => 'boolean',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
