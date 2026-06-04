<?php

namespace App\Domain\Tenant\PriceLists\Controllers;

use App\Actions\Tenant\PriceList\CreatePriceListAction;
use App\Actions\Tenant\PriceList\DeletePriceListAction;
use App\Actions\Tenant\PriceList\UpdatePriceListAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StorePriceListRequest;
use App\Http\Requests\Customer\UpdatePriceListRequest;
use App\Models\PriceList;
use App\Services\Tenant\PriceListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function __construct(
        private readonly PriceListService $priceListService,
        private readonly CreatePriceListAction $createPriceListAction,
        private readonly UpdatePriceListAction $updatePriceListAction,
        private readonly DeletePriceListAction $deletePriceListAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PriceList::class);

        return Inertia::render('tenant/price-lists/Index', [
            'priceLists' => $this->priceListService->list($request->user())->map(fn (PriceList $priceList) => $this->toPayload($priceList))->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PriceList::class);

        return Inertia::render('tenant/price-lists/Form', [
            'mode' => 'create',
            'priceList' => null,
            'variantOptions' => $this->priceListService->variantOptions($request->user()),
        ]);
    }

    public function show(PriceList $priceList): Response
    {
        $this->authorize('view', $priceList);

        $priceList->load([
            'items.productVariant:id,product_id,name,sku,base_unit',
            'items.productVariant.product:id,name',
        ]);
        $priceList->loadCount(['items', 'customers', 'customerGroups']);

        return Inertia::render('tenant/price-lists/Show', [
            'priceList' => $this->toPayload($priceList, true),
        ]);
    }

    public function store(StorePriceListRequest $request): RedirectResponse
    {
        $this->authorize('create', PriceList::class);

        $this->createPriceListAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.price-lists.index')->with('success', 'Tạo bảng giá thành công.');
    }

    public function edit(Request $request, PriceList $priceList): Response
    {
        $this->authorize('update', $priceList);

        $priceList->load([
            'items.productVariant:id,product_id,name,sku,base_unit',
            'items.productVariant.product:id,name',
        ]);
        $priceList->loadCount(['items', 'customers', 'customerGroups']);

        return Inertia::render('tenant/price-lists/Form', [
            'mode' => 'edit',
            'priceList' => $this->toPayload($priceList, true),
            'variantOptions' => $this->priceListService->variantOptions($request->user()),
        ]);
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): RedirectResponse
    {
        $this->authorize('update', $priceList);

        $this->updatePriceListAction->execute($request->user(), $priceList, $request->validated());

        return redirect()->route('tenant.price-lists.index')->with('success', 'Cập nhật bảng giá thành công.');
    }

    public function destroy(Request $request, PriceList $priceList): RedirectResponse
    {
        $this->authorize('delete', $priceList);

        $this->deletePriceListAction->execute($request->user(), $priceList);

        return redirect()->route('tenant.price-lists.index')->with('success', 'Xóa bảng giá thành công.');
    }

    private function toPayload(PriceList $priceList, bool $includeItems = false): array
    {
        $payload = [
            'id' => $priceList->id,
            'code' => $priceList->code,
            'name' => $priceList->name,
            'is_default' => (bool) $priceList->is_default,
            'is_active' => (bool) $priceList->is_active,
            'priority' => (int) $priceList->priority,
            'description' => $priceList->description,
            'start_date' => optional($priceList->start_date)->toDateString(),
            'end_date' => optional($priceList->end_date)->toDateString(),
            'items_count' => (int) ($priceList->items_count ?? 0),
            'customers_count' => (int) ($priceList->customers_count ?? 0),
            'customer_groups_count' => (int) ($priceList->customer_groups_count ?? 0),
        ];

        if (! $includeItems) {
            return $payload;
        }

        $payload['items'] = $priceList->items
            ->sortBy([
                ['product_variant_id', 'asc'],
                ['unit_name', 'asc'],
                ['min_quantity', 'asc'],
            ])
            ->map(fn ($item) => [
                'id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'variant_name' => $item->productVariant?->name,
                'variant_sku' => $item->productVariant?->sku,
                'product_name' => $item->productVariant?->product?->name,
                'unit_name' => $item->unit_name,
                'price' => (float) $item->price,
                'min_quantity' => (int) $item->min_quantity,
            ])
            ->values();

        return $payload;
    }
}

