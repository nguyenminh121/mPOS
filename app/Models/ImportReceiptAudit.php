<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportReceiptAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_receipt_id',
        'action',
        'before_json',
        'after_json',
        'change_note',
        'field_changed',
        'items_affected',
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

    public function importReceipt(): BelongsTo
    {
        return $this->belongsTo(ImportReceipt::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
