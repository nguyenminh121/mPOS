<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitPriceHistory extends Model
{
    public $timestamps = false;
    protected $table = 'unit_price_history';

    protected $fillable = [
        'product_variant_id',
        'product_unit_id',
        'unit_name',
        'conversion_rate',
        'cost_price',
        'retail_price',
        'wholesale_price',
        'effective_date',
        'import_receipt_id',
        'change_reason',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'effective_date' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function importReceipt(): BelongsTo
    {
        return $this->belongsTo(ImportReceipt::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
