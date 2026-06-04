<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => ['sometimes', 'required', 'string', 'max:50'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'order_id' => ['sometimes', 'nullable', 'integer', Rule::exists('orders', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'supplier_id' => ['sometimes', 'nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'payment_status' => ['sometimes', 'required', Rule::in(['PENDING', 'PAID', 'CANCELLED'])],
            'expense_date' => ['sometimes', 'nullable', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
