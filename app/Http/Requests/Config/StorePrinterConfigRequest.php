<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrinterConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'printer_name' => ['required', 'string', 'max:255'],
            'printer_type' => ['nullable', Rule::in(['THERMAL', 'LASER', 'INKJET', 'DOT_MATRIX'])],
            'connection_type' => ['nullable', Rule::in(['USB', 'NETWORK', 'BLUETOOTH', 'SERIAL'])],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'document_type' => ['required', 'string', 'max:50'],
            'template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'paper_width' => ['nullable', 'integer', 'min:1'],
            'auto_cut' => ['boolean'],
            'copies' => ['nullable', 'integer', 'min:1'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
