<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
