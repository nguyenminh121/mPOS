<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EInvoiceBatch extends Model
{
    use BelongsToTenant;

    protected $table = 'e_invoice_batches';

    protected $fillable = [
        'tenant_id',
        'code',
        'export_date',
        'mode',
        'status',
        'provider',
        'cqt_reference',
        'total_invoices',
        'total_amount',
        'exported_at',
        'created_by',
        'payload_snapshot',
        'response_snapshot',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'export_date' => 'date',
            'total_amount' => 'decimal:2',
            'exported_at' => 'datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(EInvoiceDetail::class, 'e_invoice_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
