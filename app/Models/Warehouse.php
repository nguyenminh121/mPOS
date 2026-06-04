<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'code',
        'name',
        'address',
        'phone',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockTakes(): HasMany
    {
        return $this->hasMany(StockTake::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function importReceipts(): HasMany
    {
        return $this->hasMany(ImportReceipt::class);
    }

    public function posShifts(): HasMany
    {
        return $this->hasMany(PosShift::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function outgoingTransferOrders(): HasMany
    {
        return $this->hasMany(TransferOrder::class, 'source_warehouse_id');
    }

    public function incomingTransferOrders(): HasMany
    {
        return $this->hasMany(TransferOrder::class, 'destination_warehouse_id');
    }
}
