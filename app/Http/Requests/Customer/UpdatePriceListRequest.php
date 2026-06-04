<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('price_lists')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('priceList')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],

            // Nested items
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['sometimes', 'integer', 'exists:price_list_items,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.unit_name' => ['nullable', 'string', 'max:50'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.min_quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
