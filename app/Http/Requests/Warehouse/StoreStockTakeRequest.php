<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTakeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'status' => ['sometimes', 'required', Rule::in(['DRAFT', 'IN_PROGRESS'])],
            'note' => ['nullable', 'string', 'max:5000'],

            // Nested items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.counted_qty' => ['required', 'numeric', 'min:0'],
            'items.*.count_uom_name' => ['nullable', 'string', 'max:80'],
            'items.*.count_conversion_rate' => ['required', 'integer', 'min:1'],
            'items.*.system_qty' => ['sometimes', 'integer', 'min:0'],
            'items.*.actual_qty' => ['sometimes', 'integer', 'min:0'],
            'items.*.diff_qty' => ['sometimes', 'integer'],
            'items.*.reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
