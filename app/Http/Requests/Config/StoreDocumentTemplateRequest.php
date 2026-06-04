<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('document_templates')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'body_html' => ['required', 'string'],
            'css' => ['nullable', 'string'],
            'paper_size' => ['nullable', 'string', 'max:20'],
            'orientation' => ['nullable', Rule::in(['PORTRAIT', 'LANDSCAPE'])],
            'margin_top' => ['nullable', 'numeric', 'min:0'],
            'margin_bottom' => ['nullable', 'numeric', 'min:0'],
            'margin_left' => ['nullable', 'numeric', 'min:0'],
            'margin_right' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
