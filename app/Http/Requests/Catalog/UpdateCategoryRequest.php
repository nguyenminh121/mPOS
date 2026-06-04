<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('categories')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('category')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
