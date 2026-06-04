<?php

namespace App\Http\Requests\ReturnOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnOrderRequest extends FormRequest
{
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'order_id' => [
                'required',
                'integer',
                Rule::exists('orders', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'COMPLETED'),
            ],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'type' => ['required', Rule::in(['FULL', 'PARTIAL'])],
            'refund_method' => ['nullable', Rule::in(['CASH', 'BANK_TRANSFER', 'STORE_CREDIT'])],
            'reason' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],

            // Nested items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.refund_amount' => ['required', 'numeric', 'min:0'],
            'items.*.condition' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.exists' => 'Chỉ cho phép tạo trả hàng từ đơn đã hoàn thành và thuộc tenant hiện tại.',
        ];
    }
}
