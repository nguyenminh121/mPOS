<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['sometimes', 'required', Rule::in(['PENDING', 'PAID', 'CANCELLED'])],
            'expense_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
