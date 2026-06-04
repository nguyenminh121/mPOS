<?php

namespace App\Domain\Tenant\Sales\Controllers;

use App\Actions\Tenant\Pos\CreatePreorderSaleAction;
use App\Actions\Tenant\Pos\CreateReturnSaleAction;
use App\Actions\Tenant\Pos\CreateSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StorePosReturnRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\ProductVariant;
use App\Models\ReturnOrder;
use App\Models\SurchargeCatalog;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\PosService;
use App\Services\Tenant\InvoiceUsageConfigService;
use App\Services\Tenant\StockPolicyConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function __construct(
        private readonly PosService $posService,
        private readonly StockPolicyConfigService $stockPolicyConfigService,
        private readonly InvoiceUsageConfigService $invoiceUsageConfigService,
        private readonly CreateSaleAction $createSaleAction,
        private readonly CreatePreorderSaleAction $createPreorderSaleAction,
        private readonly CreateReturnSaleAction $createReturnSaleAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();
        $editingOrder = $this->resolveEditableOrderFromRequest($request);

        return Inertia::render('tenant/sales/Index', [
            'sales' => fn () => $this->posService->listSales($user),
            'warehouses' => fn () => Warehouse::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'sellers' => function () use ($user) {
                $sellers = User::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->role('admin')
                    ->orderBy('name')
                    ->get(['id', 'name']);

                if ($sellers->isEmpty()) {
                    return User::query()
                        ->where('tenant_id', $user->tenant_id)
                        ->whereKey($user->id)
                        ->get(['id', 'name']);
                }

                return $sellers;
            },
            'customers' => fn () => Customer::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'code', 'name', 'phone', 'price_list_id']),
            'variants' => fn () => ProductVariant::query()
                ->whereHas('product', fn ($query) => $query->where('tenant_id', $user->tenant_id))
                ->with([
                    'product:id,name',
                    'units:id,product_variant_id,uom_id,conversion_factor',
                    'units.uom:id,name',
                    'prices:id,product_variant_id,uom_id,retail_price',
                    'stocks:id,product_variant_id,warehouse_id,quantity',
                    'stocks.warehouse:id,name',
                ])
                ->orderBy('name')
                ->get(['id', 'product_id', 'name', 'sku', 'barcode', 'base_unit', 'price'])
                ->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $variant->product?->name,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'base_unit' => $variant->base_unit,
                    'price' => (float) $variant->price,
                    'unit_options' => $variant->units
                        ->map(function ($unit) use ($variant) {
                            $priceByUom = $variant->prices->firstWhere('uom_id', $unit->uom_id);

                            return [
                                'name' => $unit->uom?->name,
                                'conversion_rate' => (float) ($unit->conversion_factor ?? 1),
                                'retail_price' => $priceByUom ? (float) ($priceByUom->retail_price ?? 0) : null,
                            ];
                        })
                        ->filter(fn (array $unit) => is_string($unit['name']) && $unit['name'] !== '')
                        ->values(),
                    'total_stock' => (float) $variant->stocks->sum('quantity'),
                    'stock_by_warehouse' => $variant->stocks
                        ->groupBy('warehouse_id')
                        ->map(fn ($rows) => [
                            'warehouse_id' => (int) $rows->first()->warehouse_id,
                            'warehouse_name' => $rows->first()->warehouse?->name,
                            'quantity' => (float) $rows->sum('quantity'),
                        ])
                        ->values(),
                ])
                ->values(),
            'surcharges' => fn () => SurchargeCatalog::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'value', 'is_refundable']),
            'priceLists' => fn () => PriceList::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->with(['items' => fn ($query) => $query
                    ->select(['id', 'price_list_id', 'product_variant_id', 'unit_name', 'price', 'min_quantity'])
                    ->orderBy('product_variant_id')
                    ->orderBy('min_quantity')])
                ->orderByDesc('is_default')
                ->orderByDesc('priority')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_default', 'priority'])
                ->map(fn (PriceList $priceList) => [
                    'id' => $priceList->id,
                    'code' => $priceList->code,
                    'name' => $priceList->name,
                    'is_default' => (bool) $priceList->is_default,
                    'priority' => (int) $priceList->priority,
                    'items' => $priceList->items->map(fn ($item) => [
                        'product_variant_id' => (int) $item->product_variant_id,
                        'unit_name' => $item->unit_name,
                        'price' => (float) $item->price,
                        'min_quantity' => (int) $item->min_quantity,
                    ])->values(),
                ])->values(),
            'categories' => fn () => Category::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'description', 'parent_id', 'is_active']),
            'uoms' => fn () => Uom::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'symbol']),
            'stockPolicy' => fn () => $this->stockPolicyConfigService->getPolicy($user),
            'invoicePolicy' => fn () => $this->invoiceUsageConfigService->getPolicy($user),
            'editingOrder' => $editingOrder,
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->createSaleAction->execute($request->user(), $request->validated());

        return redirect()
            ->route('tenant.sales.index')
            ->with('success', 'POS sale created successfully.')
            ->with('created_order_id', $order->id)
            ->with('created_order_code', $order->code)
            ->with('created_mode', 'RETAIL');
    }

    public function storePreorder(StoreOrderRequest $request): RedirectResponse
    {
        $this->authorize('createPreorder', Order::class);

        $order = $this->createPreorderSaleAction->execute($request->user(), $request->validated());

        return redirect()
            ->route('tenant.sales.index')
            ->with('success', 'POS pre-order saved successfully.')
            ->with('created_order_id', $order->id)
            ->with('created_order_code', $order->code)
            ->with('created_mode', 'PREORDER')
            ->with('created_preorder_status', strtoupper((string) $order->status));
    }

    public function storeReturn(StorePosReturnRequest $request): RedirectResponse
    {
        $this->authorize('createFromPos', ReturnOrder::class);

        $this->createReturnSaleAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.sales.index')->with('success', 'POS return created successfully.');
    }

    private function resolveEditableOrderFromRequest(Request $request): ?array
    {
        $orderRef = trim((string) $request->query('editOrderRef', ''));
        if ($orderRef === '') {
            return null;
        }

        $order = $this->resolveTenantOrderFromRef($request, $orderRef);
        $this->authorize('update', $order);

        $status = strtoupper((string) $order->status);
        if ($status === 'CANCELLED') {
            throw ValidationException::withMessages([
                'order' => 'Đơn hàng đã hủy không thể sửa nội dung.',
            ]);
        }

        $order->loadMissing(['items.productVariant.product', 'surcharges', 'invoice']);

        return [
            'id' => (int) $order->id,
            'code' => (string) $order->code,
            'status' => $status,
            'type' => strtoupper((string) $order->type),
            'customer_id' => is_numeric($order->customer_id) ? (int) $order->customer_id : null,
            'seller_id' => is_numeric($order->user_id) ? (int) $order->user_id : null,
            'warehouse_id' => is_numeric($order->warehouse_id) ? (int) $order->warehouse_id : null,
            'order_date' => optional($order->order_date)->format('Y-m-d\TH:i'),
            'discount' => (float) ($order->discount ?? 0),
            'paid_amount' => (float) ($order->paid_amount ?? 0),
            'note' => (string) ($order->note ?? ''),
            'invoice_id' => is_numeric($order->invoice_id) ? (int) $order->invoice_id : null,
            'invoice_status' => $order->invoice?->status,
            'surcharge_id' => $order->surcharges->first()?->id,
            'items' => $order->items->map(fn ($item) => [
                'order_item_id' => (int) $item->id,
                'product_variant_id' => (int) $item->product_variant_id,
                'unit_name' => (string) ($item->unit_name ?? ''),
                'conversion_rate' => (int) ($item->conversion_rate ?? 1),
                'quantity' => (int) ($item->quantity ?? 0),
                'price' => (float) ($item->price ?? 0),
                'note' => (string) ($item->note ?? ''),
            ])->values()->all(),
        ];
    }

    private function resolveTenantOrderFromRef(Request $request, string $orderRef): Order
    {
        $tenantId = (int) $request->user()->tenant_id;
        $normalizedRef = trim($orderRef);

        abort_if($normalizedRef === '', 404);

        $orderId = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $normalizedRef)
            ->value('id');

        abort_if(! is_numeric($orderId), 404);

        return Order::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $orderId);
    }
}
