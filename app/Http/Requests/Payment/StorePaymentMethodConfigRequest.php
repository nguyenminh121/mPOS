<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodConfigRequest extends FormRequest
{
    private const ALLOWED_METHODS = ['CASH', 'BANK_TRANSFER', 'COD', 'DEBT', 'OTHER'];

    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('payment_method_configs')->where('tenant_id', $this->user()->tenant_id),
                Rule::in(self::ALLOWED_METHODS),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(self::ALLOWED_METHODS)],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
        ];
    }
}
