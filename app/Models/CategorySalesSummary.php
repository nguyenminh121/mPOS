<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorySalesSummary extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'summary_date',
        'category_id',
        'revenue',
        'profit',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'revenue' => 'decimal:2',
            'profit' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
