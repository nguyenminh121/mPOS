<?php

namespace App\Domain\Tenant\Surcharges\Controllers;

use App\Actions\Tenant\Surcharge\CreateSurchargeCatalogAction;
use App\Actions\Tenant\Surcharge\DeleteSurchargeCatalogAction;
use App\Actions\Tenant\Surcharge\UpdateSurchargeCatalogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreSurchargeCatalogRequest;
use App\Http\Requests\Marketing\UpdateSurchargeCatalogRequest;
use App\Models\SurchargeCatalog;
use App\Services\Tenant\SurchargeCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SurchargeCatalogController extends Controller
{
    public function __construct(
        private readonly SurchargeCatalogService $surchargeCatalogService,
        private readonly CreateSurchargeCatalogAction $createSurchargeCatalogAction,
        private readonly UpdateSurchargeCatalogAction $updateSurchargeCatalogAction,
        private readonly DeleteSurchargeCatalogAction $deleteSurchargeCatalogAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SurchargeCatalog::class);

        return Inertia::render('tenant/surcharges/Index', [
            'surcharges' => $this->surchargeCatalogService->list($request->user())->map(
                fn (SurchargeCatalog $surcharge) => $this->toPayload($surcharge)
            )->values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SurchargeCatalog::class);

        return Inertia::render('tenant/surcharges/Form', [
            'mode' => 'create',
            'surcharge' => null,
        ]);
    }

    public function store(StoreSurchargeCatalogRequest $request): RedirectResponse
    {
        $this->authorize('create', SurchargeCatalog::class);

        $this->createSurchargeCatalogAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.surcharges.index')->with('success', 'Tạo chi phí phát sinh thành công.');
    }

    public function show(SurchargeCatalog $surcharge): Response
    {
        $this->authorize('view', $surcharge);
        $surcharge->load('creator:id,name');

        return Inertia::render('tenant/surcharges/Show', [
            'surcharge' => $this->toPayload($surcharge),
        ]);
    }

    public function edit(SurchargeCatalog $surcharge): Response
    {
        $this->authorize('update', $surcharge);

        return Inertia::render('tenant/surcharges/Form', [
            'mode' => 'edit',
            'surcharge' => $this->toPayload($surcharge),
        ]);
    }

    public function update(UpdateSurchargeCatalogRequest $request, SurchargeCatalog $surcharge): RedirectResponse
    {
        $this->authorize('update', $surcharge);

        $this->updateSurchargeCatalogAction->execute($request->user(), $surcharge, $request->validated());

        return redirect()->route('tenant.surcharges.index')->with('success', 'Cập nhật chi phí phát sinh thành công.');
    }

    public function destroy(Request $request, SurchargeCatalog $surcharge): RedirectResponse
    {
        $this->authorize('delete', $surcharge);

        $this->deleteSurchargeCatalogAction->execute($request->user(), $surcharge);

        return redirect()->route('tenant.surcharges.index')->with('success', 'Xóa chi phí phát sinh thành công.');
    }

    private function toPayload(SurchargeCatalog $surcharge): array
    {
        return [
            'id' => $surcharge->id,
            'code' => $surcharge->code,
            'name' => $surcharge->name,
            'description' => $surcharge->description,
            'type' => $surcharge->type,
            'value' => (float) $surcharge->value,
            'is_auto_apply' => (bool) $surcharge->is_auto_apply,
            'is_refundable' => (bool) $surcharge->is_refundable,
            'is_active' => (bool) $surcharge->is_active,
            'category' => $surcharge->category,
            'created_by_name' => $surcharge->creator?->name,
            'created_at' => optional($surcharge->created_at)?->toDateTimeString(),
        ];
    }
}
