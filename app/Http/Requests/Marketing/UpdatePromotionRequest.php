<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('promotions')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('promotion')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type' => ['sometimes', 'required', Rule::in(['DISCOUNT_ITEM', 'DISCOUNT_ORDER', 'BUY_X_GET_Y', 'FREE_SHIPPING', 'GIFT'])],
            'discount_type' => ['sometimes', 'required', Rule::in(['PERCENT', 'FIXED_AMOUNT'])],
            'value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'min_order_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after:start_date'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'target_customer_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],

            // Nested targets
            'targets' => ['sometimes', 'array'],
            'targets.*.id' => ['sometimes', 'integer', 'exists:promotion_targets,id'],
            'targets.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'targets.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
