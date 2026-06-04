<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:source_warehouse_id'],
            'note' => ['nullable', 'string', 'max:5000'],

            // Nested items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
