<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'allow_negative_stock' => ['required', 'boolean'],
        ];
    }
}
