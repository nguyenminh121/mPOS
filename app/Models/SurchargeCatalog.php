<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurchargeCatalog extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'surcharge_catalog';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'is_auto_apply',
        'is_refundable',
        'is_active',
        'category',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_auto_apply' => 'boolean',
            'is_refundable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
