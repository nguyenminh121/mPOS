<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseLocationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['AREA', 'FLOOR', 'AISLE', 'RACK', 'SHELF', 'BIN'])],
            'parent_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }
}
