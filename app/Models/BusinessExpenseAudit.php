<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExpenseAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'action',
        'before_json',
        'after_json',
        'change_note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(BusinessExpense::class, 'expense_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
