<?php

namespace App\Domain\Tenant\Stocks\Controllers;

use App\Actions\Tenant\ImportReceipt\CreateImportReceiptAction;
use App\Actions\Tenant\ImportReceipt\DeleteImportReceiptAction;
use App\Actions\Tenant\ImportReceipt\UpdateImportReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StoreImportReceiptRequest;
use App\Http\Requests\Purchase\UpdateImportReceiptRequest;
use App\Models\ImportReceipt;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\Tenant\ImportReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportReceiptController extends Controller
{
    public function __construct(
        private readonly ImportReceiptService $importReceiptService,
        private readonly CreateImportReceiptAction $createImportReceiptAction,
        private readonly UpdateImportReceiptAction $updateImportReceiptAction,
        private readonly DeleteImportReceiptAction $deleteImportReceiptAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ImportReceipt::class);

        return Inertia::render('tenant/import-receipts/Index', [
            'receipts' => $this->importReceiptService->list($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ImportReceipt::class);

        return Inertia::render('tenant/import-receipts/Form', [
            'mode' => 'create',
            'receipt' => null,
            'warehouses' => Warehouse::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'suppliers' => Supplier::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'variants' => ProductVariant::query()
                ->whereHas('product', fn ($query) => $query->where('tenant_id', $request->user()->tenant_id))
                ->with([
                    'product:id,name',
                    'units:id,product_variant_id,uom_id,conversion_factor',
                    'units.uom:id,name,symbol',
                    'stocks:id,product_variant_id,warehouse_id,quantity',
                ])
                ->orderBy('id')
                ->get(['id', 'product_id', 'name', 'sku', 'barcode', 'base_unit', 'price', 'wholesale_price', 'is_default']),
            'uoms' => Uom::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name', 'symbol', 'base_uom_id', 'is_active']),
        ]);
    }

    public function store(StoreImportReceiptRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', ImportReceipt::class);

        $receipt = $this->createImportReceiptAction->execute($request->user(), $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tạo phiếu nhập thành công.',
                'receipt' => ['id' => $receipt->id, 'code' => $receipt->code],
            ]);
        }

        return redirect()->route('tenant.import-receipts.index')->with('success', 'Tạo phiếu nhập thành công.');
    }

    public function show(ImportReceipt $importReceipt): Response
    {
        $this->authorize('view', $importReceipt);

        $importReceipt->load([
            'supplier:id,name',
            'warehouse:id,name',
            'creator:id,name',
            'items.productVariant.product:id,name',
        ]);

        return Inertia::render('tenant/import-receipts/Show', [
            'receipt' => $importReceipt,
        ]);
    }

    public function edit(Request $request, ImportReceipt $importReceipt): Response
    {
        $this->authorize('update', $importReceipt);

        $importReceipt->load(['items.productVariant.product:id,name']);

        return Inertia::render('tenant/import-receipts/Form', [
            'mode' => 'edit',
            'receipt' => $importReceipt,
            'warehouses' => Warehouse::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'suppliers' => Supplier::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'variants' => ProductVariant::query()
                ->whereHas('product', fn ($query) => $query->where('tenant_id', $request->user()->tenant_id))
                ->with([
                    'product:id,name',
                    'units:id,product_variant_id,uom_id,conversion_factor',
                    'units.uom:id,name,symbol',
                    'stocks:id,product_variant_id,warehouse_id,quantity',
                ])
                ->orderBy('id')
                ->get(['id', 'product_id', 'name', 'sku', 'barcode', 'base_unit', 'price', 'wholesale_price', 'is_default']),
            'uoms' => Uom::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name', 'symbol', 'base_uom_id', 'is_active']),
        ]);
    }

    public function update(UpdateImportReceiptRequest $request, ImportReceipt $importReceipt): RedirectResponse
    {
        $this->authorize('update', $importReceipt);

        $this->updateImportReceiptAction->execute($request->user(), $importReceipt, $request->validated());

        return redirect()->route('tenant.import-receipts.index')->with('success', 'Cập nhật phiếu nhập thành công.');
    }

    public function destroy(Request $request, ImportReceipt $importReceipt): RedirectResponse
    {
        $this->authorize('delete', $importReceipt);

        $this->deleteImportReceiptAction->execute($request->user(), $importReceipt);

        return redirect()->route('tenant.import-receipts.index')->with('success', 'Xóa phiếu nhập thành công.');
    }
}
