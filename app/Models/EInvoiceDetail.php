<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EInvoiceDetail extends Model
{
    use BelongsToTenant;

    protected $table = 'e_invoice_details';

    protected $fillable = [
        'tenant_id',
        'e_invoice_batch_id',
        'invoice_id',
        'order_id',
        'invoice_code',
        'order_code',
        'customer_name',
        'invoice_total',
        'invoice_sale_at',
        'exported_status',
        'cqt_reference',
        'provider_reference',
        'error_message',
        'payload_snapshot',
        'response_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'invoice_total' => 'decimal:2',
            'invoice_sale_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(EInvoiceBatch::class, 'e_invoice_batch_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
