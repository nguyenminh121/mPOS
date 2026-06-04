<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('tax_configs')->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($this->route('taxConfig')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
