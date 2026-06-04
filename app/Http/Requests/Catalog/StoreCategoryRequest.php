<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('categories')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
