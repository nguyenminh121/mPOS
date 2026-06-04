<?php

namespace App\Domain\Tenant\Orders\Controllers;

use App\Actions\Tenant\Order\CreateOrderAction;
use App\Actions\Tenant\Order\DeleteOrderAction;
use App\Actions\Tenant\Order\UpdateOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Tenant\InvoiceUsageConfigService;
use App\Services\Tenant\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly InvoiceUsageConfigService $invoiceUsageConfigService,
        private readonly CreateOrderAction $createOrderAction,
        private readonly UpdateOrderAction $updateOrderAction,
        private readonly DeleteOrderAction $deleteOrderAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();
        $perPage = (int) $request->integer('per_page', 20);
        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        return Inertia::render('tenant/orders/Index', [
            'orders' => $this->orderService->list($user, null, $perPage),
            'customers' => Customer::query()
                ->where('tenant_id', $user->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => Category::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']),
            'invoicePolicy' => $this->invoiceUsageConfigService->getPolicy($user),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', Order::class);

        return redirect()->route('tenant.sales.index', [
            'mode' => 'RETAIL',
            'entry' => 'orders-index',
        ]);
    }

    public function show(Request $request, string $orderRef): Response
    {
        $order = $this->resolveTenantOrderFromRef($request, $orderRef);
        $this->authorize('view', $order);

        $order->load(['customer', 'warehouse', 'user', 'items.productVariant.product', 'surcharges', 'returnOrders', 'invoice:id,code,e_invoice_status,e_invoiced_at']);

        return Inertia::render('tenant/orders/Show', [
            'order' => $order,
            'invoicePolicy' => $this->invoiceUsageConfigService->getPolicy($request->user()),
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $this->createOrderAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.orders.index')->with('success', 'Order created successfully.');
    }

    public function edit(Request $request, string $orderRef): Response|RedirectResponse
    {
        $order = $this->resolveTenantOrderFromRef($request, $orderRef);
        $this->authorize('update', $order);

        return redirect()
            ->route('tenant.orders.show', $order->code)
            ->with('warning', 'Cập nhật trạng thái thực hiện trực tiếp tại trang chi tiết đơn hàng.');
    }

    public function update(UpdateOrderRequest $request, string $orderRef): RedirectResponse
    {
        $order = $this->resolveTenantOrderFromRef($request, $orderRef);
        $this->authorize('update', $order);

        $updatedOrder = $this->updateOrderAction->execute($request->user(), $order, $request->validated());

        if ($request->boolean('from_show')) {
            return redirect()->route('tenant.orders.show', $updatedOrder->code)->with('success', 'Cập nhật trạng thái đơn hàng thành công.');
        }

        if ($request->boolean('from_payment')) {
            return redirect()->route('tenant.orders.show', $updatedOrder->code)->with('success', 'Cập nhật thanh toán đơn hàng thành công.');
        }

        if ($request->boolean('from_sales_edit')) {
            return redirect()->route('tenant.orders.show', $updatedOrder->code)->with('success', 'Cập nhật đơn hàng thành công.');
        }

        return redirect()->route('tenant.orders.index')->with('success', 'Order updated successfully.');
    }

    public function destroy(Request $request, string $orderRef): RedirectResponse
    {
        $order = $this->resolveTenantOrderFromRef($request, $orderRef);
        $this->authorize('delete', $order);

        $this->deleteOrderAction->execute($request->user(), $order);

        return redirect()->route('tenant.orders.index')->with('success', 'Order deleted successfully.');
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