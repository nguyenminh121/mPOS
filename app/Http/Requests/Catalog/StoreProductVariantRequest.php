<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'base_unit' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],

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
