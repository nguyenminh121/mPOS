<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCodeSequenceConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entity_type' => [
                'required', 'string', 'max:50',
                Rule::unique('code_sequence_configs')->where('tenant_id', $this->user()->tenant_id),
            ],
            'prefix' => ['required', 'string', 'max:20'],
            'separator' => ['nullable', 'string', 'max:5'],
            'date_format' => ['nullable', 'string', 'max:20'],
            'sequence_length' => ['nullable', 'integer', 'min:1', 'max:20'],
            'reset_period' => ['nullable', Rule::in(['DAILY', 'MONTHLY', 'YEARLY', 'NEVER'])],
            'sample_output' => ['nullable', 'string', 'max:100'],
        ];
    }
}
