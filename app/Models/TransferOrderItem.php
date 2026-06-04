<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferOrderItem extends Model
{
    protected $fillable = [
        'transfer_order_id',
        'product_variant_id',
        'batch_id',
        'quantity',
        'received_quantity',
        'note',
    ];

    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(TransferOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }
}
