<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderSurcharge extends Model
{
    protected $fillable = [
        'order_id',
        'name',
        'type',
        'value',
        'amount',
        'is_refundable',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_refundable' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
