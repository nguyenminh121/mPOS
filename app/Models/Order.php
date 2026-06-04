<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $appends = [
        'type_label',
        'status_label',
    ];

    protected $fillable = [
        'tenant_id',
        'code',
        'parent_order_id',
        'revision_number',
        'revision_reason',
        'invoice_id',
        'customer_id',
        'user_id',
        'warehouse_id',
        'type',
        'status',
        'is_locked',
        'locked_at',
        'locked_by',
        'locked_reason',
        'total_amount',
        'discount',
        'surcharge',
        'final_amount',
        'paid_amount',
        'debt_amount',
        'promotion_id',
        'promo_discount',
        'note',
        'order_date',
        'completed_at',
        'processing_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'surcharge' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'debt_amount' => 'decimal:2',
            'promo_discount' => 'decimal:2',
            'order_date' => 'datetime',
            'completed_at' => 'datetime',
            'processing_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    public function childOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function surcharges(): HasMany
    {
        return $this->hasMany(OrderSurcharge::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(OrderAudit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returnOrders(): HasMany
    {
        return $this->hasMany(ReturnOrder::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function accountReceivables(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match (strtoupper((string) $this->type)) {
            'RETAIL' => 'Bán lẻ',
            'WHOLESALE' => 'Bán buôn',
            'ONLINE' => 'Bán online',
            'RETURN' => 'Trả hàng',
            default => (string) $this->type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match (strtoupper((string) $this->status)) {
            'PREORDER' => 'Đặt trước',
            'DRAFT' => 'Nháp',
            'CONFIRMED' => 'Đã xác nhận',
            'PROCESSING' => 'Đang xử lý',
            'COMPLETED' => 'Hoàn thành',
            'CANCELLED' => 'Đã hủy',
            default => (string) $this->status,
        };
    }

    public function businessExpenses(): HasMany
    {
        return $this->hasMany(BusinessExpense::class);
    }
}
