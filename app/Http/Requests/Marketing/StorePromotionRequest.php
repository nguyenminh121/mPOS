<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('promotions')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['DISCOUNT_ITEM', 'DISCOUNT_ORDER', 'BUY_X_GET_Y', 'FREE_SHIPPING', 'GIFT'])],
            'discount_type' => ['required', Rule::in(['PERCENT', 'FIXED_AMOUNT'])],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order_value' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'target_customer_type' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],

            // Nested targets
            'targets' => ['sometimes', 'array'],
            'targets.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'targets.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
