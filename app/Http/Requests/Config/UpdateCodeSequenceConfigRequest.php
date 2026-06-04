<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCodeSequenceConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'prefix' => ['sometimes', 'required', 'string', 'max:20'],
            'separator' => ['sometimes', 'nullable', 'string', 'max:5'],
            'date_format' => ['sometimes', 'nullable', 'string', 'max:20'],
            'sequence_length' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'reset_period' => ['sometimes', 'nullable', Rule::in(['DAILY', 'MONTHLY', 'YEARLY', 'NEVER'])],
            'sample_output' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
