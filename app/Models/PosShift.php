<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosShift extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'user_id',
        'status',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_difference',
        'total_sales',
        'total_returns',
        'total_transactions',
        'opened_at',
        'closed_at',
        'note',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'total_returns' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
