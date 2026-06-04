<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'code',
        'order_id',
        'root_invoice_id',
        'parent_invoice_id',
        'version',
        'customer_id',
        'subtotal',
        'tax_config_id',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'status',
        'e_invoice_status',
        'e_invoiced_at',
        'change_reason',
        'sale_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sale_at' => 'datetime',
            'e_invoiced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rootInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'root_invoice_id');
    }

    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function childInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function taxConfig(): BelongsTo
    {
        return $this->belongsTo(TaxConfig::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(InvoiceAudit::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function accountReceivables(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function eInvoiceDetail(): HasOne
    {
        return $this->hasOne(EInvoiceDetail::class);
    }
}
