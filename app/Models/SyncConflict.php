<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncConflict extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'tenant_id',
        'sync_log_id',
        'table_name',
        'record_id',
        'local_data',
        'server_data',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'local_data' => 'array',
            'server_data' => 'array',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(SyncLog::class);
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
