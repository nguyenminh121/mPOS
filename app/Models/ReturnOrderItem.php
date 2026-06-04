<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnOrderItem extends Model
{
    protected $fillable = [
        'return_order_id',
        'product_variant_id',
        'order_item_id',
        'quantity',
        'price',
        'refund_amount',
        'condition',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'refund_amount' => 'decimal:2',
        ];
    }

    public function returnOrder(): BelongsTo
    {
        return $this->belongsTo(ReturnOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
