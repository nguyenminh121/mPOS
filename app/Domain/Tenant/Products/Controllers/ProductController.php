<?php

namespace App\Domain\Tenant\Products\Controllers;

use App\Actions\Tenant\Product\CreateProductAction;
use App\Actions\Tenant\Product\DeleteProductAction;
use App\Actions\Tenant\Product\GenerateProductCodeAction;
use App\Actions\Tenant\Product\UpdateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\GenerateProductCodeRequest;
use App\Http\Requests\Catalog\StoreBatchProductRequest;
use App\Http\Requests\Catalog\StoreProductBulkImportRequest;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Imports\RawSheetImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\TaxConfig;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\Tenant\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CreateProductAction $createProductAction,
        private readonly UpdateProductAction $updateProductAction,
        private readonly DeleteProductAction $deleteProductAction,
        private readonly GenerateProductCodeAction $generateProductCodeAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $user = $request->user();

        return Inertia::render('tenant/products/Index', [
            'products' => $this->productService->list($user, $request),
            'categories' => Category::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'code', 'name', 'description', 'parent_id', 'is_active']),
            'suppliers' => Supplier::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'taxConfigs' => TaxConfig::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'uoms' => Uom::query()->where('tenant_id', $user->tenant_id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'symbol']),
            'warehouses' => Warehouse::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        $user = $request->user();

        return Inertia::render('tenant/products/Form', [
            'mode' => 'create',
            'product' => null,
            'categories' => Category::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'code', 'name', 'description', 'parent_id', 'is_active']),
            'uoms' => Uom::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name', 'symbol', 'base_uom_id', 'is_active']),
            'suppliers' => Supplier::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'taxConfigs' => TaxConfig::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'warehouseLocations' => $this->productService->warehouseLocationsForTenant($user),
            'supplierIds' => [],
        ]);
    }

    public function generateCode(GenerateProductCodeRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $generated = $this->generateProductCodeAction->execute(
            $request->user(),
            (string) $request->validated('name'),
        );

        return response()->json([
            'sku' => $generated,
            'barcode' => $generated,
        ]);
    }

    public function checkExisting(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.sku' => ['nullable', 'string'],
            'items.*.barcode' => ['nullable', 'string'],
        ]);

        $tenantId = (int) $request->user()->tenant_id;
        $items = $validated['items'] ?? [];

        $skus = collect($items)
            ->pluck('sku')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique()
            ->values();

        $barcodes = collect($items)
            ->pluck('barcode')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique()
            ->values();

        if ($skus->isEmpty() && $barcodes->isEmpty()) {
            return response()->json([
                'existing' => [
                    'skus' => [],
                    'barcodes' => [],
                ],
            ]);
        }

        $variantQuery = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $tenantId));

        $existingSkus = (clone $variantQuery)
            ->when($skus->isNotEmpty(), fn ($query) => $query->whereIn('sku', $skus->all()), fn ($query) => $query->whereRaw('1=0'))
            ->whereNotNull('sku')
            ->pluck('sku')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique()
            ->values();

        $existingBarcodes = (clone $variantQuery)
            ->when($barcodes->isNotEmpty(), fn ($query) => $query->whereIn('barcode', $barcodes->all()), fn ($query) => $query->whereRaw('1=0'))
            ->whereNotNull('barcode')
            ->pluck('barcode')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique()
            ->values();

        return response()->json([
            'existing' => [
                'skus' => $existingSkus,
                'barcodes' => $existingBarcodes,
            ],
        ]);
    }

    public function storeBatch(StoreBatchProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $results = $this->productService->importBatch(
            $request->user(),
            $request->validated('rows'),
        );

        return response()->json(['results' => $results]);
    }

    public function importBulk(StoreProductBulkImportRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $import = new RawSheetImport();
        Excel::import($import, $request->file('file'));

        $summary = $this->productService->importBulkRows(
            $request->user(),
            $import->rows,
            $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null,
        );

        return response()->json([
            'message' => 'Import file hoàn tất.',
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, string $productRef): Response
    {
        $product = $this->resolveTenantProductFromRef($request, $productRef);
        $this->authorize('view', $product);

        $product->load([
            'category',
            'variants.units.uom',
            'variants.prices.uom',
            'variants.stocks',
            'variants.productStorage.warehouseLocation',
            'variants.productStorage.warehouseLocation.warehouse',
            'images',
            'suppliers',
            'taxConfig',
        ]);

        return Inertia::render('tenant/products/Show', [
            'product' => $product,
            'warehouseLocations' => $this->productService->warehouseLocationsForTenant($request->user()),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->createProductAction->execute($request->user(), $request->validated());

        if ($request->expectsJson()) {
            $product->load(['variants:id,product_id,name,sku,barcode,base_unit,price,is_default']);

            $defaultVariant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();

            return response()->json([
                'message' => 'Product created successfully.',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'variant' => $defaultVariant ? [
                        'id' => $defaultVariant->id,
                        'name' => $defaultVariant->name,
                        'sku' => $defaultVariant->sku,
                        'barcode' => $defaultVariant->barcode,
                        'base_unit' => $defaultVariant->base_unit,
                        'price' => (float) $defaultVariant->price,
                    ] : null,
                ],
            ]);
        }

        return redirect()->route('tenant.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Request $request, string $productRef): Response
    {
        $product = $this->resolveTenantProductFromRef($request, $productRef);
        $this->authorize('update', $product);

        $user = $request->user();
        $product->load([
            'variants.units',
            'variants.prices',
            'variants.productStorage.warehouseLocation',
            'variants.productStorage.warehouseLocation.warehouse',
            'images',
            'suppliers:id',
        ]);

        return Inertia::render('tenant/products/Form', [
            'mode' => 'edit',
            'product' => $product,
            'categories' => Category::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'code', 'name', 'description', 'parent_id', 'is_active']),
            'uoms' => Uom::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name', 'symbol', 'base_uom_id', 'is_active']),
            'suppliers' => Supplier::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'taxConfigs' => TaxConfig::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'warehouseLocations' => $this->productService->warehouseLocationsForTenant($user),
            'supplierIds' => $product->suppliers->pluck('id')->values(),
        ]);
    }

    public function update(UpdateProductRequest $request, string $productRef): RedirectResponse
    {
        $product = $this->resolveTenantProductFromRef($request, $productRef);
        $this->authorize('update', $product);

        $this->updateProductAction->execute($request->user(), $product, $request->validated());

        return redirect()->route('tenant.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, string $productRef): RedirectResponse
    {
        $product = $this->resolveTenantProductFromRef($request, $productRef);
        $this->authorize('delete', $product);

        $this->deleteProductAction->execute($request->user(), $product);

        return redirect()->route('tenant.products.index')->with('success', 'Product deleted successfully.');
    }

    private function resolveTenantProductFromRef(Request $request, string $productRef): Product
    {
        $tenantId = (int) $request->user()->tenant_id;
        $normalizedRef = trim($productRef);

        abort_if($normalizedRef === '', 404);

        $productId = Product::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($normalizedRef): void {
                $query->where('sku', $normalizedRef)
                    ->orWhere('barcode', $normalizedRef);
            })
            ->value('id');

        abort_if(! is_numeric($productId), 404);

        return Product::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $productId);
    }
}

