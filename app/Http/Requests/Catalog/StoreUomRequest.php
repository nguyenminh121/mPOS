<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUomRequest extends FormRequest
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

        $this->merge([
            'name' => $normalize($this->input('name')),
            'symbol' => $normalize($this->input('symbol')),
            'base_uom_id' => $this->input('base_uom_id') === '' ? null : $this->input('base_uom_id'),
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('uoms')->where('tenant_id', $this->user()->tenant_id),
            ],
            'symbol' => [
                'nullable', 'string', 'max:50',
                Rule::unique('uoms')->where('tenant_id', $this->user()->tenant_id),
            ],
            'base_uom_id' => [
                'nullable',
                'integer',
                Rule::exists('uoms', 'id')->where(fn ($query) => $query->where('tenant_id', $this->user()->tenant_id)),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
