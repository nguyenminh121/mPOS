<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStorage extends Model
{
    protected $table = 'product_storage';

    protected $fillable = [
        'product_variant_id',
        'warehouse_location_id',
        'stock_level',
        'min_stock_level',
        'max_stock_level',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }
}
