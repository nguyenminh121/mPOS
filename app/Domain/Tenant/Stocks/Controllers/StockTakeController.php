<?php

namespace App\Domain\Tenant\Stocks\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreStockTakeRequest;
use App\Http\Requests\Warehouse\UpdateStockTakeRequest;
use App\Models\ProductVariant;
use App\Models\StockTake;
use App\Models\Warehouse;
use App\Services\Tenant\StockTakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTakeController extends Controller
{
    public function __construct(private readonly StockTakeService $stockTakeService)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockTake::class);

        return Inertia::render('tenant/stock-takes/Index', [
            'stockTakes' => $this->stockTakeService->list($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', StockTake::class);

        return Inertia::render('tenant/stock-takes/Form', [
            'mode' => 'create',
            'stockTake' => null,
            'warehouses' => Warehouse::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('is_active', true)
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
                ->get(['id', 'product_id', 'name', 'sku', 'barcode', 'base_unit', 'cost_price', 'is_default']),
        ]);
    }

    public function store(StoreStockTakeRequest $request): RedirectResponse
    {
        $this->authorize('create', StockTake::class);

        $this->stockTakeService->create($request->user(), $request->validated());

        return redirect()->route('tenant.stock-takes.index')->with('success', 'Tạo phiếu kiểm kê thành công.');
    }

    public function show(Request $request, StockTake $stockTake): Response
    {
        $this->authorize('view', $stockTake);

        return Inertia::render('tenant/stock-takes/Show', [
            'stockTake' => $this->stockTakeService->detail($request->user(), $stockTake),
            'permissions' => [
                'canUpdate' => $request->user()->can('update', $stockTake),
                'canComplete' => $request->user()->can('complete', $stockTake),
                'canUndo' => $request->user()->can('undo', $stockTake),
                'canDelete' => $request->user()->can('delete', $stockTake),
            ],
        ]);
    }

    public function edit(Request $request, StockTake $stockTake): Response
    {
        $this->authorize('update', $stockTake);

        $stockTake->load([
            'items.productVariant.product:id,name',
            'items.productVariant.units:id,product_variant_id,uom_id,conversion_factor',
            'items.productVariant.units.uom:id,name,symbol',
            'items.productVariant.stocks:id,product_variant_id,warehouse_id,quantity',
        ]);

        return Inertia::render('tenant/stock-takes/Form', [
            'mode' => 'edit',
            'stockTake' => $stockTake,
            'warehouses' => Warehouse::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('is_active', true)
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
                ->get(['id', 'product_id', 'name', 'sku', 'barcode', 'base_unit', 'cost_price', 'is_default']),
        ]);
    }

    public function update(UpdateStockTakeRequest $request, StockTake $stockTake): RedirectResponse
    {
        $this->authorize('update', $stockTake);

        $this->stockTakeService->update($request->user(), $stockTake, $request->validated());

        return redirect()->route('tenant.stock-takes.show', $stockTake)->with('success', 'Cập nhật phiếu kiểm kê thành công.');
    }

    public function destroy(Request $request, StockTake $stockTake): RedirectResponse
    {
        $this->authorize('delete', $stockTake);

        $this->stockTakeService->delete($request->user(), $stockTake);

        return redirect()->route('tenant.stock-takes.index')->with('success', 'Xóa phiếu kiểm kê thành công.');
    }

    public function complete(Request $request, StockTake $stockTake): RedirectResponse
    {
        $this->authorize('complete', $stockTake);

        $this->stockTakeService->complete($request->user(), $stockTake);

        return redirect()->route('tenant.stock-takes.show', $stockTake)->with('success', 'Đã hoàn tất kiểm kê và cập nhật tồn kho.');
    }

    public function undo(Request $request, StockTake $stockTake): RedirectResponse
    {
        $this->authorize('undo', $stockTake);

        $this->stockTakeService->undo($request->user(), $stockTake);

            return redirect()->route('tenant.stock-takes.show', $stockTake)->with('success', sprintf(
                'Đã hoàn tác phiếu kiểm kê %s, cân bằng lại tồn kho và chuyển phiếu sang trạng thái đã hủy.',
                $stockTake->code
            ));
    }
}
