<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'base_unit' => ['sometimes', 'required', 'string', 'max:50'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'discount_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'wholesale_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],

            // Nested attributes
            'attributes' => ['sometimes', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.value_number' => ['nullable', 'numeric'],
            'attributes.*.value_text' => ['nullable', 'string', 'max:500'],
            'attributes.*.value_date' => ['nullable', 'date'],
            'attributes.*.value_bool' => ['nullable', 'boolean'],
            'attributes.*.description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
