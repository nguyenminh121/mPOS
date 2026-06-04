<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        $tenantId = (int) $this->user()?->tenant_id;

        return [
            'order_id' => ['sometimes', 'nullable', 'integer', Rule::exists('orders', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'tax_config_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tax_configs', 'id')->where('tenant_id', $tenantId)],
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'sale_at' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['ISSUED', 'REPLACED', 'CANCELLED'])],
            'change_reason' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required_with:items', 'integer', 'exists:product_variants,id'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.unit_name' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
