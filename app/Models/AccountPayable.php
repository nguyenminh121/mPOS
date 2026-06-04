<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPayable extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'amount',
        'balance_after',
        'type',
        'status',
        'import_receipt_id',
        'payment_id',
        'due_date',
        'paid_at',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function importReceipt(): BelongsTo
    {
        return $this->belongsTo(ImportReceipt::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
