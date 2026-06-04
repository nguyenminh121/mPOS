<?php

namespace App\Domain\Tenant\Invoices\Controllers;

use App\Actions\Tenant\Invoice\CreateInvoiceAction;
use App\Actions\Tenant\Invoice\DeleteInvoiceAction;
use App\Actions\Tenant\Invoice\UpdateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreInvoiceRequest;
use App\Http\Requests\Order\UpdateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\TaxConfig;
use App\Models\TenantProfile;
use App\Models\User;
use App\Services\Tenant\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly CreateInvoiceAction $createInvoiceAction,
        private readonly UpdateInvoiceAction $updateInvoiceAction,
        private readonly DeleteInvoiceAction $deleteInvoiceAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        return Inertia::render('tenant/invoices/Index', [
            'invoices' => $this->invoiceService->list($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        $user = $request->user();
        $sourceInvoiceId = $request->integer('source_invoice_id');
        $replacementSeed = null;

        if ($sourceInvoiceId > 0) {
            $sourceInvoice = Invoice::query()
                ->where('tenant_id', $user->tenant_id)
                ->with(['items'])
                ->findOrFail($sourceInvoiceId);

            $this->authorize('view', $sourceInvoice);

            $replacementSeed = [
                'parent_invoice_id' => $sourceInvoice->id,
                'order_id' => $sourceInvoice->order_id,
                'customer_id' => $sourceInvoice->customer_id,
                'tax_config_id' => $sourceInvoice->tax_config_id,
                'tax_rate' => (float) $sourceInvoice->tax_rate,
                'sale_at' => optional($sourceInvoice->sale_at)->format('Y-m-d\TH:i:sP'),
                'change_reason' => 'Điều chỉnh hóa đơn từ '.$sourceInvoice->code,
                'items' => $sourceInvoice->items->map(fn ($item) => [
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name,
                    'unit_name' => $item->unit_name,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'discount' => (float) $item->discount,
                    'tax_rate' => (float) $item->tax_rate,
                ])->values(),
            ];
        }

        return Inertia::render('tenant/invoices/Form', [
            'mode' => 'create',
            'invoice' => $replacementSeed,
            'orders' => $this->buildInvoiceFormOrders($user),
            'customers' => Customer::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'taxConfigs' => TaxConfig::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name', 'rate']),
            'variants' => $this->buildInvoiceFormVariants($user),
        ]);
    }

    public function show(Request $request, string $invoiceRef): Response
    {
        $invoice = $this->resolveTenantInvoiceFromRef($request, $invoiceRef);
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'order', 'items.productVariant', 'taxConfig']);

        $issuerUnitName = TenantProfile::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->value('company_name');

        if (! $issuerUnitName) {
            $issuerUnitName = $request->user()->tenant?->name;
        }

        $invoice->setAttribute('issuer_unit_name', $issuerUnitName ?: 'N/A');

        return Inertia::render('tenant/invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $this->createInvoiceAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.invoices.index')->with('success', 'Invoice created successfully.');
    }

    public function edit(Request $request, string $invoiceRef): Response
    {
        $invoice = $this->resolveTenantInvoiceFromRef($request, $invoiceRef);
        $this->authorize('update', $invoice);

        $user = $request->user();
        $invoice->load(['items']);

        return Inertia::render('tenant/invoices/Form', [
            'mode' => 'edit',
            'invoice' => $invoice,
            'taxConfigs' => TaxConfig::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name', 'rate']),
            'orders' => $this->buildInvoiceFormOrders($user),
            'customers' => Customer::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']),
            'variants' => $this->buildInvoiceFormVariants($user),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, string $invoiceRef): RedirectResponse
    {
        $invoice = $this->resolveTenantInvoiceFromRef($request, $invoiceRef);
        $this->authorize('update', $invoice);

        $this->updateInvoiceAction->execute($request->user(), $invoice, $request->validated());

        return redirect()->route('tenant.invoices.index')->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Request $request, string $invoiceRef): RedirectResponse
    {
        $invoice = $this->resolveTenantInvoiceFromRef($request, $invoiceRef);
        $this->authorize('delete', $invoice);

        $this->deleteInvoiceAction->execute($request->user(), $invoice);

        return redirect()->route('tenant.invoices.index')->with('success', 'Invoice deleted successfully.');
    }

    private function buildInvoiceFormOrders(User $user)
    {
        return Order::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['items.productVariant.product'])
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'code', 'customer_id', 'order_date'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'code' => $order->code,
                'customer_id' => $order->customer_id,
                'order_date' => $order->order_date?->format('Y-m-d\\TH:i:sP'),
                'items' => $order->items->map(function ($item) {
                    $productName = trim((string) ($item->productVariant?->product?->name ?? ''));
                    $variantName = trim((string) ($item->productVariant?->name ?? ''));

                    $displayName = $productName;
                    if ($productName === '') {
                        $displayName = $variantName;
                    } elseif ($variantName !== '' && strcasecmp($productName, $variantName) !== 0) {
                        $displayName = $productName.' - '.$variantName;
                    }

                    return [
                        'product_variant_id' => (int) $item->product_variant_id,
                        'product_name' => $displayName,
                        'unit_name' => $item->unit_name,
                        'quantity' => (float) $item->quantity,
                        'price' => (float) $item->price,
                        'discount' => (float) ($item->discount ?? 0),
                        'tax_rate' => (float) ($item->tax_rate ?? 0),
                    ];
                })->values(),
            ])
            ->values();
    }

    private function buildInvoiceFormVariants(User $user)
    {
        return ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->with([
                'product:id,name',
                'units:id,product_variant_id,uom_id,conversion_factor',
                'units.uom:id,name',
                'prices:id,product_variant_id,uom_id,retail_price',
            ])
            ->orderBy('name')
            ->get(['id', 'product_id', 'name', 'sku', 'barcode', 'base_unit', 'price'])
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
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
            ])
            ->values();
    }

    private function resolveTenantInvoiceFromRef(Request $request, string $invoiceRef): Invoice
    {
        $tenantId = (int) $request->user()->tenant_id;
        $normalizedRef = trim($invoiceRef);

        abort_if($normalizedRef === '', 404);

        $invoiceId = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $normalizedRef)
            ->value('id');

        abort_if(! is_numeric($invoiceId), 404);

        return Invoice::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $invoiceId);
    }
}