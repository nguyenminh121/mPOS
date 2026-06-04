<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('customer_groups')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('customerGroup')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'default_price_list_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('price_lists', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
