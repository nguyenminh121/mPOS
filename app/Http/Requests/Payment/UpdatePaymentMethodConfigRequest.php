<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodConfigRequest extends FormRequest
{
    private const ALLOWED_METHODS = ['CASH', 'BANK_TRANSFER', 'COD', 'DEBT', 'OTHER'];

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('payment_method_configs')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('paymentMethodConfig')),
                Rule::in(self::ALLOWED_METHODS),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', Rule::in(self::ALLOWED_METHODS)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'config' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
