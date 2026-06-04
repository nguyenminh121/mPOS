<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryCostingConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'method' => ['sometimes', 'required', Rule::in(['FIFO', 'LIFO', 'WEIGHTED_AVG', 'SPECIFIC'])],
            'pricing_strategy' => ['sometimes', 'required', Rule::in(['GLOBAL', 'PER_BATCH', 'MARGIN_BASED'])],
            'default_margin_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
