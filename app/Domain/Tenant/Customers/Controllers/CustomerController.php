<?php

namespace App\Domain\Tenant\Customers\Controllers;

use App\Actions\Tenant\Customer\CreateCustomerAction;
use App\Actions\Tenant\Customer\DeleteCustomerAction;
use App\Actions\Tenant\Customer\SyncCustomerDebtAction;
use App\Actions\Tenant\Customer\UpdateCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\PaymentMethodConfig;
use App\Models\PriceList;
use App\Models\ReturnOrder;
use App\Models\User;
use App\Services\Tenant\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CreateCustomerAction $createCustomerAction,
        private readonly UpdateCustomerAction $updateCustomerAction,
        private readonly DeleteCustomerAction $deleteCustomerAction,
        private readonly SyncCustomerDebtAction $syncCustomerDebtAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $this->syncCustomerDebtAction->execute($request->user());

        $filters = [
            'group_id' => $request->input('group_id'),
            'type' => $request->input('type'),
            'address' => $request->input('address'),
        ];

        $customers = $this->customerService->list($request->user(), $filters);

        return Inertia::render('tenant/customers/Index', [
            'customers' => $customers->map(fn (Customer $customer) => $this->toPayload($customer))->values(),
            'filters' => [
                'group_id' => is_numeric($filters['group_id']) ? (int) $filters['group_id'] : null,
                'type' => trim((string) ($filters['type'] ?? '')),
                'address' => trim((string) ($filters['address'] ?? '')),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);

        $user = $request->user();

        return Inertia::render('tenant/customers/Form', [
            'mode' => 'create',
            'customer' => null,
            'customerGroups' => CustomerGroup::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'code', 'name']),
            'priceLists' => PriceList::query()->where('tenant_id', $user->tenant_id)->orderByDesc('is_default')->orderByDesc('priority')->orderBy('name')->get(['id', 'code', 'name', 'is_default']),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $this->syncCustomerDebtAction->execute($request->user(), (int) $customer->id);
        $customer->refresh();

        $customer->load([
            'group:id,name,code',
            'priceList:id,name,code,is_default',
            'addresses:id,tenant_id,customer_id,name,address,receiver_name,receiver_phone,default_address,created_at,updated_at',
            'defaultAddress:id,customer_id,address,receiver_name,receiver_phone,default_address',
        ]);

        $tenantId = (int) $request->user()->tenant_id;

        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->with([
                'user:id,name',
                'items:id,order_id,product_variant_id,unit_name,quantity,price,discount,total',
                'items.productVariant:id,name,sku,barcode',
                'returnOrders:id,order_id,code,status,total_amount,return_date,created_at',
            ])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'code',
                'status',
                'type',
                'order_date',
                'created_at',
                'user_id',
                'note',
                'total_amount',
                'discount',
                'surcharge',
                'final_amount',
                'paid_amount',
                'debt_amount',
            ]);

        $returnOrders = ReturnOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->with('creator:id,name')
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get(['id', 'order_id', 'code', 'status', 'total_amount', 'return_date', 'created_at', 'created_by']);

        $returnTotal = (float) ReturnOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'CANCELLED')
            ->sum('total_amount');

        $totalSold = (float) $orders
            ->filter(fn (Order $order) => strtoupper((string) $order->status) !== 'CANCELLED')
            ->sum(fn (Order $order) => (float) ($order->final_amount ?? 0));

        $debts = AccountReceivable::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->with(['order:id,code,status'])
            ->latest('id')
            ->get(['id', 'customer_id', 'amount', 'balance_after', 'type', 'status', 'order_id', 'paid_at', 'note', 'created_at']);

        $paymentMethods = PaymentMethodConfig::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'is_default']);

        $paymentCollectors = User::query()
            ->where('tenant_id', $tenantId)
            ->role(['admin', 'manager', 'cashier'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('tenant/customers/Show', [
            'customer' => $this->toPayload($customer),
            'addresses' => $customer->addresses->map(fn ($address) => [
                'id' => (int) $address->id,
                'name' => $address->name,
                'address' => $address->address,
                'receiver_name' => $address->receiver_name,
                'receiver_phone' => $address->receiver_phone,
                'default_address' => strtoupper((string) $address->default_address) === 'YES' ? 'YES' : 'NO',
                'created_at' => $address->created_at,
            ])->values(),
            'orders' => $orders->map(fn (Order $order) => [
                'id' => (int) $order->id,
                'code' => $order->code,
                'status' => $order->status,
                'type' => $order->type,
                'order_date' => $order->order_date,
                'created_at' => $order->created_at,
                'created_by' => $order->user?->name,
                'note' => $order->note,
                'total_amount' => (float) ($order->total_amount ?? 0),
                'discount' => (float) ($order->discount ?? 0),
                'surcharge' => (float) ($order->surcharge ?? 0),
                'final_amount' => (float) ($order->final_amount ?? 0),
                'paid_amount' => (float) ($order->paid_amount ?? 0),
                'debt_amount' => (float) ($order->debt_amount ?? 0),
                'items' => $order->items->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'unit_name' => $item->unit_name,
                    'quantity' => (float) ($item->quantity ?? 0),
                    'price' => (float) ($item->price ?? 0),
                    'discount' => (float) ($item->discount ?? 0),
                    'total' => (float) ($item->total ?? 0),
                    'product_variant' => $item->productVariant ? [
                        'id' => (int) $item->productVariant->id,
                        'name' => $item->productVariant->name,
                        'sku' => $item->productVariant->sku,
                        'barcode' => $item->productVariant->barcode,
                    ] : null,
                ])->values(),
                'return_orders' => $order->returnOrders->map(fn ($returnOrder) => [
                    'id' => (int) $returnOrder->id,
                    'code' => $returnOrder->code,
                    'status' => $returnOrder->status,
                    'total_amount' => (float) ($returnOrder->total_amount ?? 0),
                    'return_date' => $returnOrder->return_date,
                    'created_at' => $returnOrder->created_at,
                ])->values(),
            ])->values(),
            'returnOrders' => $returnOrders->map(fn (ReturnOrder $returnOrder) => [
                'id' => (int) $returnOrder->id,
                'order_id' => $returnOrder->order_id ? (int) $returnOrder->order_id : null,
                'code' => $returnOrder->code,
                'status' => $returnOrder->status,
                'return_date' => $returnOrder->return_date,
                'created_at' => $returnOrder->created_at,
                'total_amount' => (float) ($returnOrder->total_amount ?? 0),
                'created_by' => $returnOrder->creator?->name,
            ])->values(),
            'debtEntries' => $debts->map(fn ($entry) => [
                'id' => (int) $entry->id,
                'type' => $entry->type,
                'status' => $entry->status,
                'amount' => (float) ($entry->amount ?? 0),
                'balance_after' => (float) ($entry->balance_after ?? 0),
                'order_id' => $entry->order_id ? (int) $entry->order_id : null,
                'order_code' => $entry->order?->code,
                'order_status' => $entry->order?->status,
                'paid_at' => $entry->paid_at,
                'created_at' => $entry->created_at,
                'note' => $entry->note,
            ])->values(),
            'paymentMethods' => $paymentMethods->map(fn (PaymentMethodConfig $row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'is_default' => (bool) $row->is_default,
            ])->values(),
            'paymentCollectors' => $paymentCollectors->map(fn (User $row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
            ])->values(),
            'authUserId' => (int) $request->user()->id,
            'salesSummary' => [
                'total_sold' => round($totalSold, 2),
                'total_return' => round($returnTotal, 2),
                'total_sold_minus_return' => max(0, round($totalSold - $returnTotal, 2)),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->createCustomerAction->execute($request->user(), $request->validated());
        $this->syncCustomerDebtAction->execute($request->user(), (int) $customer->id);
        $customer->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tạo khách hàng thành công.',
                'customer' => $this->toPayload($customer),
            ]);
        }

        return redirect()->route('tenant.customers.index')->with('success', 'Tạo khách hàng thành công.');
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorize('update', $customer);

        $user = $request->user();
        $customer->load(['group:id,name,code', 'priceList:id,name,code,is_default']);

        return Inertia::render('tenant/customers/Form', [
            'mode' => 'edit',
            'customer' => $this->toPayload($customer),
            'customerGroups' => CustomerGroup::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'code', 'name']),
            'priceLists' => PriceList::query()->where('tenant_id', $user->tenant_id)->orderByDesc('is_default')->orderByDesc('priority')->orderBy('name')->get(['id', 'code', 'name', 'is_default']),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $updatedCustomer = $this->updateCustomerAction->execute($request->user(), $customer, $request->validated());
        $this->syncCustomerDebtAction->execute($request->user(), (int) $updatedCustomer->id);

        return redirect()->route('tenant.customers.index')->with('success', 'Cập nhật khách hàng thành công.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->deleteCustomerAction->execute($request->user(), $customer);

        return redirect()->route('tenant.customers.index')->with('success', 'Xóa khách hàng thành công.');
    }

    private function toPayload(Customer $customer): array
    {
        $customerRef = trim((string) ($customer->code ?? ''));

        return [
            'id' => $customer->id,
            'code' => $customer->code,
            'customer_ref' => $customerRef !== '' ? $customerRef : (string) $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'type' => $customer->type,
            'tax_code' => $customer->tax_code,
            'company_name' => $customer->company_name,
            'debt_amount' => (float) $customer->debt_amount,
            'current_debt' => (float) ($customer->current_debt ?? $customer->debt_amount),
            'total_spent' => (float) $customer->total_spent,
            'total_sold' => (float) ($customer->total_sold ?? $customer->total_spent),
            'total_return' => (float) ($customer->total_return ?? 0),
            'total_sold_minus_return' => (float) ($customer->total_sold_minus_return ?? $customer->total_spent),
            'credit_limit' => (float) $customer->credit_limit,
            'default_due_days' => (int) $customer->default_due_days,
            'description' => $customer->description,
            'address' => $customer->defaultAddress?->address ?? $customer->address,
            'group_id' => $customer->group_id,
            'price_list_id' => $customer->price_list_id,
            'is_active' => (bool) $customer->is_active,
            'group_name' => $customer->group?->name,
            'group_code' => $customer->group?->code,
            'price_list_name' => $customer->priceList?->name,
            'price_list_code' => $customer->priceList?->code,
            'price_list_is_default' => $customer->priceList?->is_default,
            'display_address' => (string) ($customer->display_address ?? ($customer->defaultAddress?->address ?? $customer->address ?? '')),
        ];
    }
}

