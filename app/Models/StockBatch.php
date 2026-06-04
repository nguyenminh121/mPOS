<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'import_item_id',
        'batch_code',
        'cost_price',
        'initial_quantity',
        'remaining_quantity',
        'selling_price',
        'wholesale_price',
        'received_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'received_at' => 'datetime',
            'expires_at' => 'date',
            'is_active' => 'boolean',
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

    public function importItem(): BelongsTo
    {
        return $this->belongsTo(ImportItem::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(StockBatchPrice::class, 'batch_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'batch_id');
    }

    public function transferOrderItems(): HasMany
    {
        return $this->hasMany(TransferOrderItem::class, 'batch_id');
    }
}
