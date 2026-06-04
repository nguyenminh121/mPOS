<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanModuleAccess extends Model
{
    protected $table = 'plan_module_access';

    protected $fillable = [
        'plan_id',
        'module_code',
        'is_enabled',
        'limit_value',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
