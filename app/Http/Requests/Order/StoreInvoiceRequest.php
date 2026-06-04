<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        $tenantId = (int) $this->user()?->tenant_id;

        return [
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_config_id' => ['nullable', 'integer', Rule::exists('tax_configs', 'id')->where('tenant_id', $tenantId)],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'sale_at' => ['required', 'date'],
            'change_reason' => ['nullable', 'string', 'max:5000'],
            'create_order_from_invoice' => ['sometimes', 'boolean'],

            // Parent invoice (for replacement invoices)
            'parent_invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],

            // Nested items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.unit_name' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
