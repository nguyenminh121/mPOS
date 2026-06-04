<?php

namespace App\Services\Tenant;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Uom;
use App\Models\WarehouseLocation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly ImportReceiptService $importReceiptService,
    ) {
    }

    public function warehouseLocationsForTenant(User $user)
    {
        return WarehouseLocation::query()
            ->whereHas('warehouse', fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->with(['warehouse:id,name'])
            ->orderBy('warehouse_id')
            ->orderBy('type')
            ->orderBy('code')
            ->get([
                'id',
                'warehouse_id',
                'parent_id',
                'code',
                'name',
                'type',
                'is_active',
            ]);
    }

    public function list(User $user, ?Request $request = null)
    {
        $query = Product::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['category', 'variants.stocks', 'variants.units.uom', 'images'])
            ->latest();

        if ($request !== null) {
            $search = trim((string) $request->string('search')->toString());
            if ($search !== '') {
                $searchTokens = array_values(array_filter(
                    preg_split('/\s+/u', $search) ?: [],
                    fn ($token) => $token !== ''
                ));

                $query->where(function ($builder) use ($search, $searchTokens) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                    if (count($searchTokens) > 1) {
                        $builder->orWhere(function ($tokenBuilder) use ($searchTokens) {
                            foreach ($searchTokens as $searchToken) {
                                $tokenBuilder->where('name', 'like', "%{$searchToken}%");
                            }
                        });
                    }
                });

            }

            $categoryId = $request->input('category_id');
            if ($categoryId !== null && $categoryId !== '') {
                $query->where('category_id', (int) $categoryId);
            }

            $isActive = $request->input('is_active');
            if ($isActive !== null && $isActive !== '') {
                $normalized = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($normalized !== null) {
                    $query->where('is_active', $normalized);
                }
            }
        }

        $perPage = (int) ($request?->input('per_page', 20) ?? 20);
        $perPage = max(10, min($perPage, 100));

        return $query->paginate($perPage)->withQueryString();
    }

    public function importBulkRows(User $user, array $rows, ?int $warehouseId = null): array
    {
        $headerIndex = $this->detectHeaderIndex($rows);
        if ($headerIndex < 0) {
            throw ValidationException::withMessages([
                'file' => 'Không tìm thấy dòng tiêu đề hợp lệ trong file import.',
            ]);
        }

        $headers = array_map(fn ($value) => (string) ($value ?? ''), (array) ($rows[$headerIndex] ?? []));

        $existingSkus = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->whereNotNull('sku')
            ->pluck('sku')
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();

        $existingBarcodes = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->whereNotNull('barcode')
            ->pluck('barcode')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();

        $skuSet = array_fill_keys($existingSkus, true);
        $barcodeSet = array_fill_keys($existingBarcodes, true);

        $summary = [
            'added' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        for ($rowIndex = $headerIndex + 1; $rowIndex < count($rows); $rowIndex++) {
            $values = array_map(fn ($value) => trim((string) ($value ?? '')), (array) ($rows[$rowIndex] ?? []));
            $mapped = $this->mapImportedProductRow($headers, $values);

            if ($mapped === null) {
                $summary['skipped'] += 1;
                continue;
            }

            if ($this->isTemplateNumberingRow($mapped)) {
                $summary['skipped'] += 1;
                continue;
            }

            $validationMessage = $this->validateImportedProductRow($mapped);
            if ($validationMessage !== null) {
                $summary['failed'] += 1;
                $summary['errors'][] = [
                    'line' => $rowIndex + 1,
                    'message' => $validationMessage,
                    'sku' => $mapped['sku'],
                    'barcode' => $mapped['barcode'],
                    'name' => $mapped['name'],
                ];
                continue;
            }

            $skuKey = mb_strtolower($mapped['sku']);
            $barcodeKey = $mapped['barcode'];
            if (($skuKey !== '' && isset($skuSet[$skuKey])) || ($barcodeKey !== '' && isset($barcodeSet[$barcodeKey]))) {
                $summary['duplicates'] += 1;
                continue;
            }

            try {
                $created = $this->create($user, [
                    'name' => $mapped['name'],
                    'sku' => $mapped['sku'] !== '' ? $mapped['sku'] : null,
                    'barcode' => $mapped['barcode'] !== '' ? $mapped['barcode'] : null,
                    'base_unit' => $mapped['base_unit'],
                    'is_active' => true,
                    'warehouse_id' => $warehouseId,
                    'initial_stock' => $mapped['initial_inventory'],
                    'variants' => [[
                        'name' => $mapped['name'],
                        'sku' => $mapped['sku'] !== '' ? $mapped['sku'] : null,
                        'barcode' => $mapped['barcode'] !== '' ? $mapped['barcode'] : null,
                        'base_unit' => $mapped['base_unit'],
                        'price' => $mapped['retail_price'],
                        'cost_price' => $mapped['cost_price'],
                        'wholesale_price' => $mapped['wholesale_price'],
                        'is_active' => true,
                        'is_default' => true,
                    ]],
                ]);

                $summary['added'] += 1;

                $variant = $created->variants->first();
                $createdSku = trim((string) ($variant?->sku ?? ''));
                $createdBarcode = trim((string) ($variant?->barcode ?? ''));

                if ($createdSku !== '') {
                    $skuSet[mb_strtolower($createdSku)] = true;
                }
                if ($createdBarcode !== '') {
                    $barcodeSet[$createdBarcode] = true;
                }
            } catch (ValidationException $exception) {
                $summary['failed'] += 1;
                $message = collect($exception->errors())->flatten()->first() ?: 'Dữ liệu không hợp lệ.';
                $summary['errors'][] = [
                    'line' => $rowIndex + 1,
                    'message' => (string) $message,
                    'sku' => $mapped['sku'],
                    'barcode' => $mapped['barcode'],
                    'name' => $mapped['name'],
                ];
            } catch (\Throwable $exception) {
                $summary['failed'] += 1;
                $summary['errors'][] = [
                    'line' => $rowIndex + 1,
                    'message' => $exception->getMessage(),
                    'sku' => $mapped['sku'],
                    'barcode' => $mapped['barcode'],
                    'name' => $mapped['name'],
                ];
            }
        }

        return $summary;
    }

    private function detectHeaderIndex(array $rows): int
    {
        foreach ($rows as $index => $row) {
            if ($index > 30 || ! is_array($row)) {
                continue;
            }

            $line = collect($row)
                ->map(fn ($value) => $this->normalizeImportHeader((string) ($value ?? '')))
                ->implode(' | ');

            if (str_contains($line, 'ma vach') && (str_contains($line, 'ma sku') || str_contains($line, 'sku'))) {
                return $index;
            }
        }

        return -1;
    }

    private function normalizeImportHeader(string $header): string
    {
        $header = Str::of($header)
            ->ascii()
            ->lower()
            ->replace('*', ' ')
            ->toString();

        $header = str_replace('đ', 'd', $header);
        $header = preg_replace('/[^a-z0-9\s]/u', ' ', $header) ?? $header;
        $header = preg_replace('/\s+/u', ' ', $header) ?? $header;

        return trim($header);
    }

    private function mapImportedProductRow(array $headers, array $values): ?array
    {
        $map = [
            'ma hang hoa' => 'sku',
            'ma sku' => 'sku',
            'sku' => 'sku',
            'ma vach' => 'barcode',
            'barcode' => 'barcode',
            'ten san pham' => 'name',
            'ten hang hoa' => 'name',
            'don vi tinh' => 'base_unit',
            'uom' => 'base_unit',
            'gia nhap' => 'cost_price',
            'don gia ton kho' => 'cost_price',
            'gia ban le' => 'retail_price',
            'gia ban buon' => 'wholesale_price',
            'ton kho ban dau' => 'initial_inventory',
        ];

        $row = [
            'name' => '',
            'sku' => '',
            'barcode' => '',
            'base_unit' => '',
            'cost_price' => null,
            'retail_price' => null,
            'wholesale_price' => null,
            'initial_inventory' => 0,
        ];

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeImportHeader((string) $header);
            $field = $map[$normalizedHeader] ?? null;
            if ($field === null) {
                continue;
            }

            $value = trim((string) ($values[$index] ?? ''));
            if ($value === '') {
                continue;
            }

            if (in_array($field, ['cost_price', 'retail_price', 'wholesale_price'], true)) {
                $parsed = $this->toNullableFloat(str_replace(',', '', $value));
                $row[$field] = $parsed;
                continue;
            }

            if ($field === 'initial_inventory') {
                $row['initial_inventory'] = max(0, (int) ((float) str_replace(',', '', $value)));
                continue;
            }

            $row[$field] = $value;
        }

        if ($row['name'] === '' && $row['sku'] === '' && $row['barcode'] === '') {
            return null;
        }

        return $row;
    }

    private function isTemplateNumberingRow(array $row): bool
    {
        return preg_match('/^\d+$/', (string) $row['name'])
            && preg_match('/^\d+$/', (string) $row['sku'])
            && preg_match('/^\d+$/', (string) $row['barcode']);
    }

    private function validateImportedProductRow(array $row): ?string
    {
        if (trim((string) $row['name']) === '') {
            return 'Tên hàng hóa là bắt buộc.';
        }

        if (trim((string) $row['base_unit']) === '') {
            return 'Đơn vị tính là bắt buộc.';
        }

        if (trim((string) $row['sku']) === '' && trim((string) $row['barcode']) === '') {
            return 'Cần ít nhất SKU hoặc Barcode.';
        }

        if (! is_numeric($row['retail_price']) || (float) $row['retail_price'] < 0) {
            return 'Giá bán lẻ là bắt buộc và phải >= 0.';
        }

        return null;
    }

    /**
     * Batch-import products in a single transaction.
     * Pre-resolves UOMs and duplicate SKU/barcode in bulk, then creates each product.
     * Aggregates initial inventory into one ImportReceipt per warehouse.
     *
     * @param  array<int, array<string, mixed>>  $rows  Validated row data from StoreBatchProductRequest
     * @return array<int, array<string, mixed>>  Per-row result: ['_key', 'ok', 'variant_id'] or ['_key', 'ok'=> false, 'errors']
     */
    public function importBatch(User $user, array $rows): array
    {
        $tenantId = (int) $user->tenant_id;

        // ── 1. Pre-resolve all unique UOM names (1–2 queries total) ──────────
        $uniqueUomNames = array_values(array_unique(array_filter(
            array_map(fn ($r) => trim((string) ($r['base_unit'] ?? '')), $rows),
            fn ($v) => $v !== '',
        )));

        $uomSharedCache = [];
        foreach ($uniqueUomNames as $rawName) {
            $this->resolveCanonicalUomName($user, $rawName, $uomSharedCache);
        }

        // ── 2. Pre-check existing SKU / barcode in 2 queries ─────────────────
        $skuList      = array_values(array_filter(array_map(fn ($r) => trim((string) ($r['sku']     ?? '')), $rows)));
        $barcodeList  = array_values(array_filter(array_map(fn ($r) => trim((string) ($r['barcode'] ?? '')), $rows)));

        $existingSkus = $skuList !== []
            ? (array) Product::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('sku', $skuList)
                ->pluck('sku')
                ->map(fn ($v) => mb_strtolower(trim((string) $v)))
                ->flip()
                ->toArray()
            : [];

        $existingBarcodes = $barcodeList !== []
            ? (array) Product::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('barcode', $barcodeList)
                ->pluck('barcode')
                ->map(fn ($v) => trim((string) $v))
                ->flip()
                ->toArray()
            : [];

        // ── 3. Split rows into valid / instantly-failed ───────────────────────
        $earlyFailed = [];
        $validRows   = [];
        $seenSkus    = [];
        $seenBarcodes = [];

        foreach ($rows as $row) {
            $key     = $row['_key'] ?? null;
            $sku     = trim((string) ($row['sku']     ?? '')) ?: null;
            $barcode = trim((string) ($row['barcode'] ?? '')) ?: null;
            $skuLow  = $sku !== null ? mb_strtolower($sku) : null;

            if ($skuLow !== null && isset($seenSkus[$skuLow])) {
                $earlyFailed[] = ['_key' => $key, 'ok' => false, 'errors' => ['sku' => ['SKU trùng với dòng khác trong danh sách.']]];
                continue;
            }
            if ($barcode !== null && isset($seenBarcodes[$barcode])) {
                $earlyFailed[] = ['_key' => $key, 'ok' => false, 'errors' => ['barcode' => ['Barcode trùng với dòng khác trong danh sách.']]];
                continue;
            }
            if ($skuLow !== null && isset($existingSkus[$skuLow])) {
                $earlyFailed[] = ['_key' => $key, 'ok' => false, 'errors' => ['sku' => ['SKU đã tồn tại trong hệ thống.']]];
                continue;
            }
            if ($barcode !== null && isset($existingBarcodes[$barcode])) {
                $earlyFailed[] = ['_key' => $key, 'ok' => false, 'errors' => ['barcode' => ['Barcode đã tồn tại trong hệ thống.']]];
                continue;
            }

            if ($skuLow !== null)  { $seenSkus[$skuLow]      = true; }
            if ($barcode !== null) { $seenBarcodes[$barcode]  = true; }
            $validRows[] = $row;
        }

        if ($validRows === []) {
            return $earlyFailed;
        }

        // ── 4. Single transaction: create products + aggregate inventory ──────
        $batchResults = DB::transaction(function () use ($user, $validRows, $uomSharedCache): array {
            $results             = [];

            foreach ($validRows as $row) {
                $key = $row['_key'] ?? null;
                $warehouseId = isset($row['warehouse_id']) ? (int) $row['warehouse_id'] : null;
                $initialStock = max(0, (int) round((float) ($row['initial_inventory'] ?? 0)));

                try {
                    $product = $this->create($user, [
                        'name'      => trim((string) ($row['name']    ?? '')),
                        'sku'       => trim((string) ($row['sku']     ?? '')) ?: null,
                        'barcode'   => trim((string) ($row['barcode'] ?? '')) ?: null,
                        'base_unit' => $uomSharedCache[$this->normalizeUomText($row['base_unit'] ?? '')] ?? trim((string) ($row['base_unit'] ?? '')),
                        'is_active' => true,
                        'warehouse_id' => $warehouseId,
                        'initial_stock' => $initialStock,
                        'variants'  => [[
                            'name'            => trim((string) ($row['name']    ?? '')),
                            'sku'             => trim((string) ($row['sku']     ?? '')) ?: null,
                            'barcode'         => trim((string) ($row['barcode'] ?? '')) ?: null,
                            'base_unit'       => $uomSharedCache[$this->normalizeUomText($row['base_unit'] ?? '')] ?? trim((string) ($row['base_unit'] ?? '')),
                            'price'           => (float) ($row['retail_price']    ?? 0),
                            'cost_price'      => isset($row['cost_price'])      && $row['cost_price']      !== null ? (float) $row['cost_price']      : null,
                            'wholesale_price' => isset($row['wholesale_price']) && $row['wholesale_price'] !== null ? (float) $row['wholesale_price'] : null,
                            'is_active'       => true,
                            'is_default'      => true,
                        ]],
                    ]);

                    $variant   = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
                    $variantId = $variant?->id;
                    $results[] = ['_key' => $key, 'ok' => true, 'variant_id' => $variantId];
                } catch (ValidationException $e) {
                    $results[] = [
                        '_key' => $key,
                        'ok' => false,
                        'errors' => $e->errors() !== [] ? $e->errors() : ['name' => [$e->getMessage()]],
                    ];
                } catch (\Throwable $e) {
                    $results[] = ['_key' => $key, 'ok' => false, 'errors' => ['name' => [$e->getMessage()]]];
                }
            }

            return $results;
        });

        return array_merge($earlyFailed, $batchResults);
    }

    public function create(User $user, array $data): Product
    {
        return DB::transaction(function () use ($user, $data): Product {
            $uomNameCache = [];

            $productPayload = Arr::except($data, ['variants', 'images', 'supplier_ids']);
            if (array_key_exists('base_unit', $productPayload)) {
                $productPayload['base_unit'] = $this->resolveCanonicalUomName($user, $productPayload['base_unit'], $uomNameCache) ?? '';
            }

            $product = Product::query()->create([
                ...$productPayload,
                'tenant_id' => $user->tenant_id,
            ]);

            $this->syncVariants($user, $product, $data['variants'] ?? [], $uomNameCache);
            $this->syncImages($product, $data['images'] ?? []);
            $this->syncSuppliers($product, $data['supplier_ids'] ?? []);
            $this->syncInitialStock($user, $product, $data);

            return $product->load(['category', 'variants', 'images']);
        });
    }

    private function syncInitialStock(User $user, Product $product, array $data): void
    {
        $initialStock = (int) ($data['initial_stock'] ?? 0);
        if ($initialStock <= 0) {
            return;
        }

        $warehouseId = isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : 0;
        if ($warehouseId <= 0) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Vui lòng chọn kho khi khai báo tồn đầu kỳ.',
            ]);
        }

        $variant = $product->variants()->where('is_default', true)->first() ?? $product->variants()->first();
        if (! $variant instanceof ProductVariant) {
            return;
        }

        $importPrice = is_numeric($variant->cost_price) ? (float) $variant->cost_price : 0.0;

        $this->importReceiptService->create($user, [
            'warehouse_id' => $warehouseId,
            'import_date' => now(),
            'note' => "Nhập tồn đầu kỳ cho sản phẩm {$product->name}",
            'items' => [[
                'product_variant_id' => (int) $variant->id,
                'unit_name' => $variant->base_unit ?: null,
                'conversion_rate' => 1,
                'quantity' => $initialStock,
                'quantity_in_base' => $initialStock,
                'import_price' => $importPrice,
            ]],
        ]);
    }

    public function update(User $user, Product $product, array $data): Product
    {
        $this->guardTenant($user, $product);

        return DB::transaction(function () use ($user, $product, $data): Product {
            $uomNameCache = [];

            $productPayload = Arr::except($data, ['variants', 'images', 'supplier_ids']);
            if (array_key_exists('base_unit', $productPayload)) {
                $productPayload['base_unit'] = $this->resolveCanonicalUomName($user, $productPayload['base_unit'], $uomNameCache) ?? '';
            }

            $product->fill($productPayload);
            $product->save();

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($user, $product, $data['variants'], $uomNameCache);
            }

            if (array_key_exists('images', $data)) {
                $this->syncImages($product, $data['images']);
            }

            if (array_key_exists('supplier_ids', $data)) {
                $this->syncSuppliers($product, $data['supplier_ids']);
            }

            return $product->refresh()->load(['category', 'variants', 'images']);
        });
    }

    public function delete(User $user, Product $product): void
    {
        $this->guardTenant($user, $product);
        $product->delete();
    }

    private function syncVariants(User $user, Product $product, array $variants, array &$uomNameCache = []): void
    {
        $existingIds = $product->variants()->pluck('id')->all();

        if ($variants === []) {
            if ($existingIds === []) {
                $defaultVariant = $product->variants()->create($this->buildDefaultVariantPayload($product));
                $this->syncVariantUnits($defaultVariant, []);
                return;
            }

            $defaultVariantId = (int) ($existingIds[0] ?? 0);
            if ($defaultVariantId > 0) {
                $product->variants()->where('id', $defaultVariantId)->update(['is_default' => true]);
                $product->variants()->where('id', '!=', $defaultVariantId)->update(['is_default' => false]);
            }

            return;
        }

        $incomingIds = collect($variants)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $defaultIndex = collect($variants)->search(fn ($variant) => filter_var($variant['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $defaultIndex = $defaultIndex === false ? 0 : (int) $defaultIndex;

        $idsToDelete = array_diff($existingIds, $incomingIds);
        if (! empty($idsToDelete)) {
            $product->variants()->whereIn('id', $idsToDelete)->delete();
        }

        $persistedDefaultVariantId = null;

        foreach ($variants as $index => $variant) {
            $variantData = Arr::except($variant, ['id', 'units', 'storage_assignments']);
            $variantData = $this->normalizeVariantPayload($variantData);
            if (array_key_exists('base_unit', $variantData)) {
                $variantData['base_unit'] = $this->resolveCanonicalUomName($user, $variantData['base_unit'], $uomNameCache) ?? '';
            }
            $variantData['is_default'] = $index === $defaultIndex;

            if (! empty($variant['id'])) {
                $product->variants()->where('id', $variant['id'])->update($variantData);
                $updatedVariant = $product->variants()->where('id', $variant['id'])->first();
                if ($updatedVariant !== null) {
                    if ($variantData['is_default'] === true) {
                        $persistedDefaultVariantId = (int) $updatedVariant->id;
                    }
                    $this->syncVariantUnits($updatedVariant, $variant['units'] ?? []);
                    $this->syncVariantStorage($updatedVariant, $variant['storage_assignments'] ?? []);
                }
            } else {
                $createdVariant = $product->variants()->create($variantData);
                if ($variantData['is_default'] === true) {
                    $persistedDefaultVariantId = (int) $createdVariant->id;
                }
                $this->syncVariantUnits($createdVariant, $variant['units'] ?? []);
                $this->syncVariantStorage($createdVariant, $variant['storage_assignments'] ?? []);
            }
        }

        if ($persistedDefaultVariantId !== null) {
            $product->variants()->where('id', '!=', $persistedDefaultVariantId)->update(['is_default' => false]);
            $product->variants()->where('id', $persistedDefaultVariantId)->update(['is_default' => true]);
        }
    }

    private function normalizeVariantPayload(array $variantData): array
    {
        $variantData['price'] = (float) ($variantData['price'] ?? 0);
        $variantData['cost_price'] = $this->toNullableFloat($variantData['cost_price'] ?? null);
        $variantData['wholesale_price'] = $this->toNullableFloat($variantData['wholesale_price'] ?? null);

        return $variantData;
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function resolveCanonicalUomName(User $user, mixed $rawName, array &$uomNameCache = []): ?string
    {
        $normalized = $this->normalizeUomText($rawName);
        if ($normalized === '') {
            return null;
        }

        if (array_key_exists($normalized, $uomNameCache)) {
            return $uomNameCache[$normalized];
        }

        $tenantUoms = Uom::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('id')
            ->get(['id', 'name']);

        $canonical = $tenantUoms->first(fn (Uom $uom) => (string) $uom->name === $normalized)
            ?? $tenantUoms->first(fn (Uom $uom) => $this->normalizeUomText($uom->name) === $normalized);
        if ($canonical instanceof Uom) {
            if ((string) $canonical->name !== $normalized) {
                try {
                    $canonical->name = $normalized;
                    $canonical->save();
                } catch (UniqueConstraintViolationException) {
                    // MySQL collation for name can be case/accent-insensitive,
                    // so resolve by DB equality first instead of strict PHP normalization.
                    $canonical = Uom::query()
                        ->where('tenant_id', $user->tenant_id)
                        ->where('name', $normalized)
                        ->orderBy('id')
                        ->first(['id', 'name'])
                        ?? Uom::query()
                        ->where('tenant_id', $user->tenant_id)
                        ->orderBy('id')
                        ->get(['id', 'name'])
                        ->first(fn (Uom $uom) => $this->normalizeUomText($uom->name) === $normalized) ?? $canonical;
                }
            }

            $uomNameCache[$normalized] = (string) $canonical->name;
            return $uomNameCache[$normalized];
        }

        try {
            $created = Uom::query()->create([
                'tenant_id' => $user->tenant_id,
                'name' => $normalized,
                'symbol' => null,
                'base_uom_id' => null,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            $created = Uom::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('name', $normalized)
                ->orderBy('id')
                ->first(['id', 'name'])
                ?? Uom::query()
                ->where('tenant_id', $user->tenant_id)
                ->orderBy('id')
                ->get(['id', 'name'])
                ->first(fn (Uom $uom) => $this->normalizeUomText($uom->name) === $normalized);

            if (! $created instanceof Uom) {
                throw ValidationException::withMessages([
                    'base_unit' => 'Không thể đồng bộ đơn vị tính.',
                ]);
            }
        }

        $uomNameCache[$normalized] = (string) $created->name;
        return $uomNameCache[$normalized];
    }

    private function normalizeUomText(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        $singleSpaced = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtoupper($singleSpaced, 'UTF-8');
    }

    private function syncVariantStorage(ProductVariant $variant, array $storageAssignments): void
    {
        $normalizedAssignments = collect($storageAssignments)
            ->filter(fn ($assignment) => is_array($assignment))
            ->map(function (array $assignment): array {
                $warehouseLocationId = (int) ($assignment['warehouse_location_id'] ?? 0);
                $minStockLevel = max(0, (int) ($assignment['min_stock_level'] ?? 0));
                $maxStockLevel = max(0, (int) ($assignment['max_stock_level'] ?? 0));

                if ($maxStockLevel > 0 && $maxStockLevel < $minStockLevel) {
                    $maxStockLevel = $minStockLevel;
                }

                return [
                    'warehouse_location_id' => $warehouseLocationId,
                    'min_stock_level' => $minStockLevel,
                    'max_stock_level' => $maxStockLevel,
                ];
            })
            ->filter(fn (array $assignment) => $assignment['warehouse_location_id'] > 0)
            ->keyBy('warehouse_location_id')
            ->values();

        $incomingLocationIds = $normalizedAssignments
            ->pluck('warehouse_location_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($incomingLocationIds === []) {
            $variant->productStorage()->delete();
            return;
        }

        $variant->productStorage()
            ->whereNotIn('warehouse_location_id', $incomingLocationIds)
            ->delete();

        foreach ($normalizedAssignments as $assignment) {
            $variant->productStorage()->updateOrCreate(
                [
                    'warehouse_location_id' => (int) $assignment['warehouse_location_id'],
                ],
                [
                    'min_stock_level' => (int) $assignment['min_stock_level'],
                    'max_stock_level' => (int) $assignment['max_stock_level'],
                ]
            );
        }
    }

    private function buildDefaultVariantPayload(Product $product): array
    {
        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'base_unit' => (string) ($product->base_unit ?? ''),
            'price' => 0,
            'discount_price' => null,
            'cost_price' => 0,
            'wholesale_price' => 0,
            'currency' => 'VND',
            'is_active' => true,
            'is_default' => true,
        ];
    }

    private function syncVariantUnits(ProductVariant $variant, array $units): void
    {
        $existingIds = $variant->units()->pluck('id')->all();
        $incomingIds = collect($units)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $incomingUomIds = collect($units)
            ->pluck('uom_id')
            ->filter(fn ($uomId) => $uomId !== null && $uomId !== '')
            ->map(fn ($uomId) => (int) $uomId)
            ->values()
            ->all();

        $idsToDelete = array_diff($existingIds, $incomingIds);
        if (! empty($idsToDelete)) {
            $variant->units()->whereIn('id', $idsToDelete)->delete();
        }

        if ($incomingUomIds === []) {
            $variant->prices()->delete();
        } else {
            $variant->prices()->whereNotIn('uom_id', $incomingUomIds)->delete();
        }

        foreach ($units as $unit) {
            $unitPayload = Arr::only($unit, ['uom_id', 'conversion_factor']);

            if (! empty($unit['id'])) {
                $variant->units()->where('id', $unit['id'])->update($unitPayload);
            } else {
                $variant->units()->create($unitPayload);
            }

            $uomId = isset($unit['uom_id']) ? (int) $unit['uom_id'] : 0;
            if ($uomId <= 0) {
                continue;
            }

            $variant->prices()->updateOrCreate(
                ['uom_id' => $uomId],
                [
                    'cost_price' => (float) ($unit['cost_price'] ?? $variant->cost_price ?? 0),
                    'retail_price' => (float) ($unit['retail_price'] ?? $variant->price ?? 0),
                    'wholesale_price' => (float) ($unit['wholesale_price'] ?? $variant->wholesale_price ?? 0),
                    'currency' => (string) ($unit['currency'] ?? $variant->currency ?? 'VND'),
                ]
            );
        }
    }

    private function syncImages(Product $product, array $images): void
    {
        $existingIds = $product->images()->pluck('id')->all();
        $incomingIds = collect($images)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $idsToDelete = array_diff($existingIds, $incomingIds);

        if (! empty($idsToDelete)) {
            $product->images()->whereIn('id', $idsToDelete)->delete();
        }

        foreach ($images as $image) {
            $uploadedFile = $image['_file'] ?? null;

            if ($uploadedFile instanceof UploadedFile) {
                $storedPath = $uploadedFile->store('products', 'public');
                $image['image_path'] = Storage::disk('public')->url($storedPath);
            }

            if (empty($image['image_path'])) {
                continue;
            }

            $imageData = Arr::except($image, ['id', '_file', '_key']);
            if (! empty($image['id'])) {
                $product->images()->where('id', $image['id'])->update($imageData);
            } else {
                $product->images()->create($imageData);
            }
        }
    }

    private function syncSuppliers(Product $product, array $supplierIds): void
    {
        $product->suppliers()->sync($supplierIds);
    }

    private function guardTenant(User $user, Product $product): void
    {
        if ($product->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'product' => 'Product does not belong to this tenant.',
            ]);
        }
    }
}
