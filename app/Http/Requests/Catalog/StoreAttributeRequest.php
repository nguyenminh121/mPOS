<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('attributes')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'data_type' => ['nullable', Rule::in(['NUMBER', 'TEXT', 'DATE', 'BOOLEAN'])],
            'is_active' => ['boolean'],
        ];
    }
}
