<?php

namespace App\Domain\Tenant\Uoms\Controllers;

use App\Actions\Tenant\Uom\CreateUomAction;
use App\Actions\Tenant\Uom\DeleteUomAction;
use App\Actions\Tenant\Uom\UpdateUomAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreUomRequest;
use App\Http\Requests\Catalog\UpdateUomRequest;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use App\Services\Tenant\UomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UomController extends Controller
{
    public function __construct(
        private readonly UomService $uomService,
        private readonly CreateUomAction $createUomAction,
        private readonly UpdateUomAction $updateUomAction,
        private readonly DeleteUomAction $deleteUomAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Uom::class);

        return Inertia::render('tenant/uoms/Index', [
            'uoms' => $this->uomService->list($request->user())->map(fn (Uom $uom) => $this->toPayload($uom))->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Uom::class);

        return Inertia::render('tenant/uoms/Form', [
            'mode' => 'create',
            'uom' => null,
            'baseOptions' => $this->uomService->baseOptions($request->user())->map(fn (Uom $uom) => $this->toBaseOption($uom))->values(),
        ]);
    }

    public function store(StoreUomRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Uom::class);

        $uom = $this->createUomAction->execute($request->user(), $request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('tenant.uoms.index')->with('success', 'Tạo đơn vị tính thành công.');
        }

        return response()->json([
            'message' => 'Tạo đơn vị tính thành công.',
            'uom' => $this->toPayload($uom),
        ]);
    }

    public function show(Uom $uom): Response
    {
        $this->authorize('view', $uom);

        $uom->load('baseUom:id,name,symbol');
        $uom->loadCount(['derivedUoms', 'productUnits', 'productPrices']);

        return Inertia::render('tenant/uoms/Show', [
            'uom' => $this->toPayload($uom),
        ]);
    }

    public function edit(Request $request, Uom $uom): Response
    {
        $this->authorize('update', $uom);

        return Inertia::render('tenant/uoms/Form', [
            'mode' => 'edit',
            'uom' => $this->toPayload($uom),
            'baseOptions' => $this->uomService->baseOptions($request->user(), $uom)->map(fn (Uom $item) => $this->toBaseOption($item))->values(),
        ]);
    }

    public function update(UpdateUomRequest $request, Uom $uom): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $uom);

        $updated = $this->updateUomAction->execute($request->user(), $uom, $request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('tenant.uoms.index')->with('success', 'Cập nhật đơn vị tính thành công.');
        }

        return response()->json([
            'message' => 'Cập nhật đơn vị tính thành công.',
            'uom' => $this->toPayload($updated),
        ]);
    }

    public function destroy(Request $request, Uom $uom): RedirectResponse
    {
        $this->authorize('delete', $uom);

        $this->deleteUomAction->execute($request->user(), $uom);

        return redirect()->route('tenant.uoms.index')->with('success', 'Xóa đơn vị tính thành công.');
    }

    private function toPayload(Uom $uom): array
    {
        return [
            'id' => $uom->id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
            'base_uom_id' => $uom->base_uom_id,
            'base_uom_name' => $uom->baseUom?->name,
            'base_uom_symbol' => $uom->baseUom?->symbol,
            'is_active' => (bool) $uom->is_active,
            'derived_uoms_count' => (int) ($uom->derived_uoms_count ?? 0),
            'product_units_count' => (int) ($uom->product_units_count ?? 0),
            'product_prices_count' => (int) ($uom->product_prices_count ?? 0),
        ];
    }

    private function toBaseOption(Uom $uom): array
    {
        return [
            'id' => $uom->id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
            'label' => $uom->symbol ? "{$uom->name} ({$uom->symbol})" : $uom->name,
        ];
    }
}
