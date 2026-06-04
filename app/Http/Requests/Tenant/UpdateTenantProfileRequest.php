<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:5000'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_email' => ['nullable', 'string', 'email', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
