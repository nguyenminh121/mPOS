<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrinterConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'printer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'printer_type' => ['sometimes', 'nullable', Rule::in(['THERMAL', 'LASER', 'INKJET', 'DOT_MATRIX'])],
            'connection_type' => ['sometimes', 'nullable', Rule::in(['USB', 'NETWORK', 'BLUETOOTH', 'SERIAL'])],
            'ip_address' => ['sometimes', 'nullable', 'string', 'max:45'],
            'port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'document_type' => ['sometimes', 'required', 'string', 'max:50'],
            'template_id' => ['sometimes', 'nullable', 'integer', 'exists:document_templates,id'],
            'paper_width' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'auto_cut' => ['sometimes', 'boolean'],
            'copies' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
