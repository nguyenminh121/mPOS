<?php

namespace App\Domain\Tenant\Returns\Controllers;

use App\Actions\Tenant\ReturnOrder\CreateReturnOrderAction;
use App\Actions\Tenant\ReturnOrder\DeleteReturnOrderAction;
use App\Actions\Tenant\ReturnOrder\UpdateReturnOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnOrder\StoreReturnOrderRequest;
use App\Http\Requests\ReturnOrder\UpdateReturnOrderRequest;
use App\Models\ReturnOrder;
use App\Services\Tenant\ReturnOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReturnOrderController extends Controller
{
    public function __construct(
        private readonly ReturnOrderService $returnOrderService,
        private readonly CreateReturnOrderAction $createReturnOrderAction,
        private readonly UpdateReturnOrderAction $updateReturnOrderAction,
        private readonly DeleteReturnOrderAction $deleteReturnOrderAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ReturnOrder::class);

        return Inertia::render('tenant/returns/Index', [
            'returns' => $this->returnOrderService->list($request->user()),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->authorize('create', ReturnOrder::class);

        return redirect()->route('tenant.sales.index', [
            'mode' => 'RETURN',
            'entry' => 'returns-index',
        ]);
    }

    public function show(ReturnOrder $return): Response
    {
        $this->authorize('view', $return);

        $return->load(['order', 'customer', 'warehouse', 'items.productVariant']);

        return Inertia::render('tenant/returns/Show', [
            'returnOrder' => $return,
        ]);
    }

    public function store(StoreReturnOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', ReturnOrder::class);

        $this->createReturnOrderAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.returns.index')->with('success', 'Return order created successfully.');
    }

    public function edit(ReturnOrder $return): RedirectResponse
    {
        $this->authorize('update', $return);

        return redirect()->route('tenant.returns.show', $return);
    }

    public function update(UpdateReturnOrderRequest $request, ReturnOrder $return): RedirectResponse
    {
        $this->authorize('update', $return);

        $this->updateReturnOrderAction->execute($request->user(), $return, $request->validated());

        return redirect()->route('tenant.returns.index')->with('success', 'Return order updated successfully.');
    }

    public function destroy(Request $request, ReturnOrder $return): RedirectResponse
    {
        $this->authorize('delete', $return);

        $this->deleteReturnOrderAction->execute($request->user(), $return);

        return redirect()->route('tenant.returns.index')->with('success', 'Return order deleted successfully.');
    }
}
