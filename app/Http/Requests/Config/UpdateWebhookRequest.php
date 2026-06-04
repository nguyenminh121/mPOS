<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'required', 'url', 'max:2048'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
