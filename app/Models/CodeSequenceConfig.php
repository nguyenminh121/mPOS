<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CodeSequenceConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'prefix',
        'separator',
        'date_format',
        'sequence_length',
        'reset_period',
        'current_value',
        'last_reset_at',
        'sample_output',
    ];

    protected function casts(): array
    {
        return [
            'last_reset_at' => 'date',
        ];
    }
}
