<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('warehouses')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }
}
