<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurchargeCatalogRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('surcharge_catalog')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['PERCENT', 'FIXED_AMOUNT'])],
            'value' => ['required', 'numeric', 'min:0'],
            'is_auto_apply' => ['boolean'],
            'is_refundable' => ['boolean'],
            'is_active' => ['boolean'],
            'category' => ['nullable', 'string', 'max:50'],
        ];
    }
}
