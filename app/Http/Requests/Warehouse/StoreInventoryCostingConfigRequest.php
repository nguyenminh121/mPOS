<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryCostingConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(['FIFO', 'LIFO', 'WEIGHTED_AVG', 'SPECIFIC'])],
            'pricing_strategy' => ['required', Rule::in(['GLOBAL', 'PER_BATCH', 'MARGIN_BASED'])],
            'default_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
