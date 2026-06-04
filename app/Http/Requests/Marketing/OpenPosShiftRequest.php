<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class OpenPosShiftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
