<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'body_html',
        'css',
        'paper_size',
        'orientation',
        'margin_top',
        'margin_bottom',
        'margin_left',
        'margin_right',
        'is_default',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'margin_top' => 'decimal:2',
            'margin_bottom' => 'decimal:2',
            'margin_left' => 'decimal:2',
            'margin_right' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function printerConfigs(): HasMany
    {
        return $this->hasMany(PrinterConfig::class, 'template_id');
    }
}
