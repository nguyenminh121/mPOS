<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('customers')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('customer')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => [
                'sometimes', 'nullable', 'string', 'max:20',
                Rule::unique('customers')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('customer')),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'tax_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'default_due_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'group_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('customer_groups', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'price_list_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('price_lists', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
