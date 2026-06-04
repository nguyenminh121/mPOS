<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('attributes')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('attribute')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'data_type' => ['sometimes', Rule::in(['NUMBER', 'TEXT', 'DATE', 'BOOLEAN'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
