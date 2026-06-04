<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreBatchProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows'                     => ['required', 'array', 'min:1', 'max:500'],
            'rows.*._key'              => ['nullable', 'string', 'max:50'],
            'rows.*.name'              => ['required', 'string', 'max:255'],
            'rows.*.sku'               => ['nullable', 'string', 'max:100'],
            'rows.*.barcode'           => ['nullable', 'string', 'max:255'],
            'rows.*.base_unit'         => ['required', 'string', 'max:50'],
            'rows.*.retail_price'      => ['required', 'numeric', 'min:0'],
            'rows.*.cost_price'        => ['nullable', 'numeric', 'min:0'],
            'rows.*.wholesale_price'   => ['nullable', 'numeric', 'min:0'],
            // warehouse_id: validated by service — avoids tenant-scoped DB query per row
            'rows.*.warehouse_id'      => ['nullable', 'integer'],
            'rows.*.initial_inventory' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $rows = $this->input('rows', []);
        $sampleRows = is_array($rows) ? array_slice($rows, 0, 3) : [];

        Log::warning('Store batch product validation failed', [
            'user_id' => $this->user()?->id,
            'tenant_id' => $this->user()?->tenant_id,
            'rows_count' => is_array($rows) ? count($rows) : 0,
            'error_keys' => array_keys($validator->errors()->toArray()),
            'errors' => $validator->errors()->toArray(),
            'sample_rows' => $sampleRows,
        ]);

        parent::failedValidation($validator);
    }
}
