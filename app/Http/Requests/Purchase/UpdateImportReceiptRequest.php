<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImportReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['sometimes', 'required', 'integer', 'exists:warehouses,id'],
            'status' => ['sometimes', 'required', Rule::in(['DRAFT', 'PENDING', 'APPROVED', 'COMPLETED', 'CANCELLED'])],
            'import_date' => ['sometimes', 'nullable', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],

            // Nested items
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['sometimes', 'integer', 'exists:import_items,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.unit_name' => ['nullable', 'string', 'max:50'],
            'items.*.conversion_rate' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.quantity_in_base' => ['required', 'integer', 'min:1'],
            'items.*.import_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
