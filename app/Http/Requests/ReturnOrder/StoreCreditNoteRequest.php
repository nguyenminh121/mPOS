<?php

namespace App\Http\Requests\ReturnOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'return_order_id' => ['nullable', 'integer', 'exists:return_orders,id'],
            'type' => ['required', Rule::in(['RETURN', 'REFUND', 'DISCOUNT', 'ERROR'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],

            // Nested items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.total' => ['required', 'numeric', 'min:0'],
        ];
    }
}
