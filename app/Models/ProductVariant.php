<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'base_unit',
        'price',
        'discount_price',
        'cost_price',
        'wholesale_price',
        'currency',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function unitPriceHistory(): HasMany
    {
        return $this->hasMany(UnitPriceHistory::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function productStorage(): HasMany
    {
        return $this->hasMany(ProductStorage::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_variant_suppliers');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function salesSummaries(): HasMany
    {
        return $this->hasMany(ProductSalesSummary::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function returnOrderItems(): HasMany
    {
        return $this->hasMany(ReturnOrderItem::class);
    }

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function promotionTargets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function importItems(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockTakeItems(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }

    public function transferOrderItems(): HasMany
    {
        return $this->hasMany(TransferOrderItem::class);
    }

    public function creditNoteItems(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }
}
