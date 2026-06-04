<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'printer_name',
        'printer_type',
        'connection_type',
        'ip_address',
        'port',
        'document_type',
        'template_id',
        'paper_width',
        'auto_cut',
        'copies',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'auto_cut' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
