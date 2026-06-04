<?php

namespace App\Domain\Tenant\Stocks\Controllers;

use App\Actions\Tenant\Warehouse\CreateWarehouseAction;
use App\Actions\Tenant\Warehouse\DeleteWarehouseAction;
use App\Actions\Tenant\Warehouse\UpdateWarehouseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Services\Tenant\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
        private readonly CreateWarehouseAction $createWarehouseAction,
        private readonly UpdateWarehouseAction $updateWarehouseAction,
        private readonly DeleteWarehouseAction $deleteWarehouseAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Warehouse::class);

        return Inertia::render('tenant/warehouses/Index', [
            'warehouses' => $this->warehouseService->list($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Warehouse::class);

        return Inertia::render('tenant/warehouses/Form', [
            'mode' => 'create',
            'warehouse' => null,
        ]);
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $this->authorize('create', Warehouse::class);

        $this->createWarehouseAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.warehouses.index')->with('success', 'Tạo kho thành công.');
    }

    public function show(Warehouse $warehouse): Response
    {
        $this->authorize('view', $warehouse);

        $warehouse->loadCount(['stocks', 'importReceipts', 'stockMovements']);

        return Inertia::render('tenant/warehouses/Show', [
            'warehouse' => $warehouse,
        ]);
    }

    public function edit(Warehouse $warehouse): Response
    {
        $this->authorize('update', $warehouse);

        return Inertia::render('tenant/warehouses/Form', [
            'mode' => 'edit',
            'warehouse' => $warehouse,
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);

        $this->updateWarehouseAction->execute($request->user(), $warehouse, $request->validated());

        return redirect()->route('tenant.warehouses.index')->with('success', 'Cập nhật kho thành công.');
    }

    public function destroy(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        $this->deleteWarehouseAction->execute($request->user(), $warehouse);

        return redirect()->route('tenant.warehouses.index')->with('success', 'Xóa kho thành công.');
    }
}
