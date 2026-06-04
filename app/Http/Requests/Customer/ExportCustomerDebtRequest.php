<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ExportCustomerDebtRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'order_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
