<?php

namespace App\Domain\Tenant\Stocks\Controllers;

use App\Actions\Tenant\WarehouseLocation\CreateWarehouseLocationAction;
use App\Actions\Tenant\WarehouseLocation\DeleteWarehouseLocationAction;
use App\Actions\Tenant\WarehouseLocation\UpdateWarehouseLocationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseLocationRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseLocationRequest;
use App\Models\WarehouseLocation;
use App\Services\Tenant\WarehouseLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseLocationController extends Controller
{
    public function __construct(
        private readonly WarehouseLocationService $warehouseLocationService,
        private readonly CreateWarehouseLocationAction $createWarehouseLocationAction,
        private readonly UpdateWarehouseLocationAction $updateWarehouseLocationAction,
        private readonly DeleteWarehouseLocationAction $deleteWarehouseLocationAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WarehouseLocation::class);

        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $options = $this->warehouseLocationService->formOptions($request->user());

        return Inertia::render('tenant/warehouse-locations/Index', [
            'locations' => $this->warehouseLocationService->list($request->user(), $warehouseId),
            'warehouses' => $options['warehouses'],
            'filters' => [
                'warehouse_id' => $warehouseId,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', WarehouseLocation::class);

        $options = $this->warehouseLocationService->formOptions($request->user());

        return Inertia::render('tenant/warehouse-locations/Form', [
            'mode' => 'create',
            'location' => null,
            'warehouses' => $options['warehouses'],
            'locations' => $options['locations'],
        ]);
    }

    public function store(StoreWarehouseLocationRequest $request): RedirectResponse
    {
        $this->authorize('create', WarehouseLocation::class);

        $this->createWarehouseLocationAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.warehouse-locations.index')->with('success', 'Tạo vị trí kho thành công.');
    }

    public function show(WarehouseLocation $warehouseLocation): Response
    {
        $this->authorize('view', $warehouseLocation);

        $warehouseLocation->load([
            'warehouse:id,name,code',
            'parent:id,name,code',
            'children:id,parent_id,name,code,type,is_active',
        ])->loadCount(['children', 'productStorage', 'stocks']);

        return Inertia::render('tenant/warehouse-locations/Show', [
            'location' => $warehouseLocation,
        ]);
    }

    public function edit(Request $request, WarehouseLocation $warehouseLocation): Response
    {
        $this->authorize('update', $warehouseLocation);

        $options = $this->warehouseLocationService->formOptions($request->user());

        return Inertia::render('tenant/warehouse-locations/Form', [
            'mode' => 'edit',
            'location' => $warehouseLocation,
            'warehouses' => $options['warehouses'],
            'locations' => $options['locations'],
        ]);
    }

    public function update(UpdateWarehouseLocationRequest $request, WarehouseLocation $warehouseLocation): RedirectResponse
    {
        $this->authorize('update', $warehouseLocation);

        $this->updateWarehouseLocationAction->execute($request->user(), $warehouseLocation, $request->validated());

        return redirect()->route('tenant.warehouse-locations.index')->with('success', 'Cập nhật vị trí kho thành công.');
    }

    public function destroy(Request $request, WarehouseLocation $warehouseLocation): RedirectResponse
    {
        $this->authorize('delete', $warehouseLocation);

        $this->deleteWarehouseLocationAction->execute($request->user(), $warehouseLocation);

        return redirect()->route('tenant.warehouse-locations.index')->with('success', 'Xóa vị trí kho thành công.');
    }
}
