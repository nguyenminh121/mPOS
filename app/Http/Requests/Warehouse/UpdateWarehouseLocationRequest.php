<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseLocationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:100'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['AREA', 'FLOOR', 'AISLE', 'RACK', 'SHELF', 'BIN'])],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouse_locations,id'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
