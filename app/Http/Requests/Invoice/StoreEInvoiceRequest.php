<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'invoice_refs' => ['required', 'array', 'min:1', 'max:200'],
            'invoice_refs.*' => ['required', 'string', 'max:50'],
            'mode' => ['nullable', Rule::in(['BATCH', 'REALTIME'])],
            'provider' => ['nullable', 'string', 'max:120'],
        ];
    }
}