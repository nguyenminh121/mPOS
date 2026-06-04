<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoicePolicyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'use_invoice_on_sale' => ['required', 'boolean'],
            'invoice_mode' => ['required', Rule::in(['STANDARD', 'POS', 'BATCH'])],
        ];
    }
}
