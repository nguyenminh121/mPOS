<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStorageLocationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('storage_locations')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('storageLocation')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
