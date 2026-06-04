<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportItem extends Model
{
    protected $fillable = [
        'import_receipt_id',
        'product_variant_id',
        'unit_name',
        'conversion_rate',
        'quantity',
        'quantity_in_base',
        'import_price',
        'tax_rate',
        'tax_amount',
        'total',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'import_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function importReceipt(): BelongsTo
    {
        return $this->belongsTo(ImportReceipt::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
