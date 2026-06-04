<?php

namespace App\Http\Requests\User;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'password' => array_merge(['sometimes'], $this->passwordRules()),
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
