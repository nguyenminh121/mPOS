<?php

namespace App\Http\Requests\ReturnOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED'])],
            'refund_method' => ['sometimes', 'nullable', Rule::in(['CASH', 'BANK_TRANSFER', 'STORE_CREDIT'])],
            'reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
