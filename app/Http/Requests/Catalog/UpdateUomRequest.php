<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUomRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalize = static function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            $singleSpaced = preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;

            return mb_strtoupper($singleSpaced, 'UTF-8');
        };

        $payload = [];

        if ($this->has('name')) {
            $payload['name'] = $normalize($this->input('name'));
        }

        if ($this->has('symbol')) {
            $payload['symbol'] = $normalize($this->input('symbol'));
        }

        if ($this->has('base_uom_id')) {
            $payload['base_uom_id'] = $this->input('base_uom_id') === '' ? null : $this->input('base_uom_id');
        }

        if ($this->has('is_active')) {
            $payload['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('uoms')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('uom')),
            ],
            'symbol' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('uoms')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('uom')),
            ],
            'base_uom_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('uoms', 'id')->where(fn ($query) => $query->where('tenant_id', $this->user()->tenant_id)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
