<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'warehouse_id' => ['sometimes', 'required', 'integer', 'exists:warehouses,id'],
            'seller_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'order_date' => ['sometimes', 'nullable', 'date'],
            'type' => ['sometimes', Rule::in(['RETAIL', 'WHOLESALE', 'ONLINE', 'RETURN'])],
            'status' => ['sometimes', 'required', Rule::in(['PREORDER', 'DRAFT', 'CONFIRMED', 'PROCESSING', 'COMPLETED', 'CANCELLED'])],
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'promotion_id' => ['sometimes', 'nullable', 'integer', 'exists:promotions,id'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'revision_reason' => ['sometimes', 'nullable', 'string', 'max:50'],
            'create_revision' => ['sometimes', 'boolean'],
            'replace_invoice' => ['sometimes', 'boolean'],
            'invoice_export_mode' => ['sometimes', Rule::in(['NOW', 'BATCH'])],

            // Nested items
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['sometimes', 'integer', 'exists:order_items,id'],
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
            'surcharges.*.id' => ['sometimes', 'integer', 'exists:order_surcharges,id'],
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
