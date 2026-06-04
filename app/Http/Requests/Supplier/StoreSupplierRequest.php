<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('suppliers')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:5000'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
