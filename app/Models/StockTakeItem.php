<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeItem extends Model
{
    protected $fillable = [
        'stock_take_id',
        'product_variant_id',
        'system_qty',
        'actual_qty',
        'diff_qty',
        'reason',
    ];

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
