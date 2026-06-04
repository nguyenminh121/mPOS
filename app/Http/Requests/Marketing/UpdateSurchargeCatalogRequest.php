<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurchargeCatalogRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('surcharge_catalog')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('surcharge')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type' => ['sometimes', 'required', Rule::in(['PERCENT', 'FIXED_AMOUNT'])],
            'value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_auto_apply' => ['sometimes', 'boolean'],
            'is_refundable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'category' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
