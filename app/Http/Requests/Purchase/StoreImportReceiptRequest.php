<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'status' => ['sometimes', 'required', Rule::in(['DRAFT', 'PENDING', 'APPROVED', 'COMPLETED'])],
            'import_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:import_receipts,id'],

            // Nested items
            'items' => ['required', 'array', 'min:1'],
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
