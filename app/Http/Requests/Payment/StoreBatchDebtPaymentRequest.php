<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchDebtPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['CASH', 'BANK_TRANSFER', 'COD', 'DEBT', 'OTHER'])],
            'order_ids' => ['nullable', 'array'],
            'order_ids.*' => [
                'integer',
                Rule::exists('orders', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:5000'],
            'paid_at' => ['nullable', 'date'],
            'collector_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }
}
