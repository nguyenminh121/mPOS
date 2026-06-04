<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'batch_id',
        'movement_type',
        'quantity',
        'balance_after',
        'cost_price_at_move',
        'order_id',
        'import_item_id',
        'stock_take_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cost_price_at_move' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function importItem(): BelongsTo
    {
        return $this->belongsTo(ImportItem::class);
    }

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
