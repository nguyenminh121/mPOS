<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransferOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(['DRAFT', 'APPROVED', 'IN_TRANSIT', 'RECEIVED', 'CANCELLED'])],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],

            // Nested items
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['sometimes', 'integer', 'exists:transfer_order_items,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.received_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
