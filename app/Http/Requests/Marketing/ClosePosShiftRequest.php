<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class ClosePosShiftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
