<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerAddressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'required', 'string', 'max:5000'],
            'receiver_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'receiver_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'default_address' => ['sometimes', 'nullable', 'in:YES,NO'],
        ];
    }
}
