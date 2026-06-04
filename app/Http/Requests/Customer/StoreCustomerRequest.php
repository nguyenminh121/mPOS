<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('customers')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => [
                'nullable', 'string', 'max:20',
                Rule::unique('customers')->where('tenant_id', $this->user()->tenant_id),
            ],
            'address' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', 'max:50'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'default_due_days' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('customer_groups', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'price_list_id' => [
                'nullable',
                'integer',
                Rule::exists('price_lists', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
