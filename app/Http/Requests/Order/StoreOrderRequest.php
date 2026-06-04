<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        $status = strtoupper((string) $this->input('status', 'CONFIRMED'));

        return [
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'create_receivable' => ['sometimes', 'boolean'],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'seller_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'order_date' => ['nullable', 'date'],
            'type' => ['nullable', Rule::in(['RETAIL', 'WHOLESALE', 'ONLINE', 'RETURN'])],
            'status' => ['nullable', Rule::in(['PREORDER', 'DRAFT', 'CONFIRMED', 'PROCESSING', 'COMPLETED', 'CANCELLED'])],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'numeric', 'min:0'],
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'note' => ['nullable', 'string', 'max:5000'],

            // Nested items
            'items' => [
                Rule::requiredIf($status !== 'DRAFT'),
                'array',
            ],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'items.*.unit_name' => ['nullable', 'string', 'max:50'],
            'items.*.conversion_rate' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'items.*.note' => ['nullable', 'string', 'max:5000'],

            // Nested surcharges
            'surcharges' => ['sometimes', 'array'],
            'surcharges.*.name' => ['required', 'string', 'max:255'],
            'surcharges.*.type' => ['required', Rule::in(['PERCENT', 'FIXED_AMOUNT'])],
            'surcharges.*.value' => ['required', 'numeric', 'min:0'],
            'surcharges.*.amount' => ['required', 'numeric', 'min:0'],
            'surcharges.*.is_refundable' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Vui lòng chọn kho xuất hàng.',
            'warehouse_id.exists' => 'Kho đã chọn không hợp lệ hoặc không thuộc cửa hàng hiện tại.',
        ];
    }
}
