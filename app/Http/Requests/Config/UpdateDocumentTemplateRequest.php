<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('document_templates')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('documentTemplate')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'body_html' => ['sometimes', 'required', 'string'],
            'css' => ['sometimes', 'nullable', 'string'],
            'paper_size' => ['sometimes', 'nullable', 'string', 'max:20'],
            'orientation' => ['sometimes', 'nullable', Rule::in(['PORTRAIT', 'LANDSCAPE'])],
            'margin_top' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'margin_bottom' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'margin_left' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'margin_right' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
