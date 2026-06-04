<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use App\Models\Uom;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function rules(): array
    {
        $currentProductId = $this->resolveCurrentProductId();

        return [
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'barcode' => [
                'sometimes', 'nullable', 'string', 'max:255',
                Rule::unique('products')->where('tenant_id', $this->user()->tenant_id)->ignore($currentProductId),
            ],
            'sku' => [
                'sometimes', 'nullable', 'string', 'max:100',
                Rule::unique('products')->where('tenant_id', $this->user()->tenant_id)->ignore($currentProductId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'base_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'origin' => ['sometimes', 'nullable', 'string', 'max:100'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tax_config_id' => ['sometimes', 'nullable', 'integer', 'exists:tax_configs,id'],
            'is_active' => ['sometimes', 'boolean'],

            // Nested variants
            'variants' => ['sometimes', 'array'],
            'variants.*.id' => ['sometimes', 'integer', 'exists:product_variants,id'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.sku' => ['nullable', 'string', 'max:255', 'required_without:variants.*.barcode'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255', 'required_without:variants.*.sku'],
            'variants.*.base_unit' => ['required', 'string', 'max:50'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.discount_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.currency' => ['nullable', 'string', 'max:10'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.is_default' => ['boolean'],
            'variants.*.storage_assignments' => ['sometimes', 'array'],
            'variants.*.storage_assignments.*.id' => ['sometimes', 'integer', 'exists:product_storage,id'],
            'variants.*.storage_assignments.*.warehouse_location_id' => [
                'required',
                'integer',
                Rule::exists('warehouse_locations', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'warehouse_id',
                        Warehouse::query()->where('tenant_id', $this->user()->tenant_id)->select('id')
                    )
                ),
            ],
            'variants.*.storage_assignments.*.min_stock_level' => ['nullable', 'integer', 'min:0'],
            'variants.*.storage_assignments.*.max_stock_level' => ['nullable', 'integer', 'min:0'],
            'variants.*.units' => ['sometimes', 'array'],
            'variants.*.units.*.id' => ['sometimes', 'integer', 'exists:product_units,id'],
            'variants.*.units.*.uom_id' => [
                'required',
                'integer',
                Rule::exists('uoms', 'id')->where(fn ($query) => $query->where('tenant_id', $this->user()->tenant_id)),
                'distinct',
            ],
            'variants.*.units.*.conversion_factor' => ['required', 'numeric', 'min:1'],
            'variants.*.units.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.units.*.retail_price' => ['required', 'numeric', 'min:0'],
            'variants.*.units.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.units.*.currency' => ['nullable', 'string', 'max:10'],

            // Nested images
            'images' => ['sometimes', 'array'],
            'images.*.id' => ['sometimes', 'integer', 'exists:product_images,id'],
            'images.*.image_path' => ['nullable', 'string', 'max:255'],
            'images.*._file' => ['nullable', 'file', 'image', 'max:5120'],
            'images.*.is_default' => ['boolean'],

            // Supplier IDs (pivot)
            'supplier_ids' => ['sometimes', 'array'],
            'supplier_ids.*' => ['integer', 'exists:suppliers,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $variants = $this->input('variants', []);
            if (! is_array($variants) || $variants === []) {
                return;
            }

            $user = $this->user();
            if ($user === null) {
                return;
            }

            $uoms = Uom::query()
                ->where('tenant_id', $user->tenant_id)
                ->get(['id', 'name', 'base_uom_id']);

            if ($uoms->isEmpty()) {
                return;
            }

            $uomById = $uoms->keyBy('id');
            $uomIdByName = $uoms->mapWithKeys(fn (Uom $uom) => [$this->normalizeText((string) $uom->name) => (int) $uom->id]);

            foreach ($variants as $variantIndex => $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                $storageAssignments = $variant['storage_assignments'] ?? [];
                if (is_array($storageAssignments) && $storageAssignments !== []) {
                    $seenWarehouseLocationIds = [];

                    foreach ($storageAssignments as $storageIndex => $storageAssignment) {
                        if (! is_array($storageAssignment)) {
                            continue;
                        }

                        $warehouseLocationId = isset($storageAssignment['warehouse_location_id'])
                            ? (int) $storageAssignment['warehouse_location_id']
                            : 0;

                        if ($warehouseLocationId > 0 && isset($seenWarehouseLocationIds[$warehouseLocationId])) {
                            $validator->errors()->add(
                                "variants.{$variantIndex}.storage_assignments.{$storageIndex}.warehouse_location_id",
                                'Vị trí lưu kho bị trùng trong cùng một biến thể.'
                            );
                        }

                        if ($warehouseLocationId > 0) {
                            $seenWarehouseLocationIds[$warehouseLocationId] = true;
                        }

                        $minStockLevel = isset($storageAssignment['min_stock_level'])
                            ? (int) $storageAssignment['min_stock_level']
                            : 0;
                        $maxStockLevel = isset($storageAssignment['max_stock_level'])
                            ? (int) $storageAssignment['max_stock_level']
                            : 0;

                        if ($maxStockLevel > 0 && $maxStockLevel < $minStockLevel) {
                            $validator->errors()->add(
                                "variants.{$variantIndex}.storage_assignments.{$storageIndex}.max_stock_level",
                                'Tồn tối đa phải lớn hơn hoặc bằng tồn tối thiểu.'
                            );
                        }
                    }
                }

                $units = $variant['units'] ?? [];
                if (! is_array($units) || $units === []) {
                    continue;
                }

                $baseUnitName = $this->normalizeText((string) ($variant['base_unit'] ?? ''));
                if ($baseUnitName === '') {
                    continue;
                }

                $baseUomId = $uomIdByName->get($baseUnitName);
                if (! is_int($baseUomId) || $baseUomId <= 0) {
                    $validator->errors()->add(
                        "variants.{$variantIndex}.base_unit",
                        'Đơn vị cơ bản của biến thể phải tồn tại trong danh mục UOM khi có quy đổi.'
                    );
                    continue;
                }

                foreach ($units as $unitIndex => $unit) {
                    if (! is_array($unit)) {
                        continue;
                    }

                    $candidateUomId = isset($unit['uom_id']) ? (int) $unit['uom_id'] : 0;
                    if ($candidateUomId <= 0) {
                        continue;
                    }
                }
            }
        });
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function resolveCurrentProductId(): int
    {
        $routeRef = trim((string) $this->route('productRef'));
        if ($routeRef === '') {
            return 0;
        }

        $tenantId = (int) ($this->user()?->tenant_id ?? 0);
        if ($tenantId <= 0) {
            return 0;
        }

        return (int) (Product::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($routeRef): void {
                $query->where('sku', $routeRef)
                    ->orWhere('barcode', $routeRef);
            })
            ->value('id') ?? 0);
    }
}
