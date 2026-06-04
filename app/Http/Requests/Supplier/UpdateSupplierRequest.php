<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('suppliers')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('supplier')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'tax_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
