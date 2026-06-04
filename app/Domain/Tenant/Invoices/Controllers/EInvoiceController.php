<?php

namespace App\Domain\Tenant\Invoices\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreEInvoiceRequest;
use App\Models\Invoice;
use App\Services\Tenant\EInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EInvoiceController extends Controller
{
    public function __construct(private readonly EInvoiceService $eInvoiceService)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        return Inertia::render('tenant/e-invoices/Index', [
            'batches' => $this->eInvoiceService->listBatches($request->user()),
            'candidateInvoices' => $this->eInvoiceService->candidateInvoices($request->user())
                ->map(fn ($invoice) => [
                    'id' => (int) $invoice->id,
                    'code' => (string) $invoice->code,
                    'status' => (string) $invoice->status,
                    'e_invoice_status' => (string) ($invoice->e_invoice_status ?? 'NOT_EXPORTED'),
                    'e_invoiced_at' => optional($invoice->e_invoiced_at)?->toIso8601String(),
                    'sale_at' => optional($invoice->sale_at)?->toIso8601String(),
                    'total_amount' => (float) ($invoice->total_amount ?? 0),
                    'order' => $invoice->order ? [
                        'id' => (int) $invoice->order->id,
                        'code' => (string) $invoice->order->code,
                        'status' => (string) $invoice->order->status,
                    ] : null,
                    'customer' => $invoice->customer ? [
                        'id' => (int) $invoice->customer->id,
                        'name' => (string) $invoice->customer->name,
                    ] : null,
                ])
                ->values(),
        ]);
    }

    public function store(StoreEInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $payload = $request->validated();

        $batch = $this->eInvoiceService->exportInvoices(
            user: $request->user(),
            invoiceRefs: $payload['invoice_refs'],
            mode: (string) ($payload['mode'] ?? 'BATCH'),
            provider: isset($payload['provider']) ? (string) $payload['provider'] : null,
        );

        return redirect()
            ->route('tenant.e-invoices.show', $batch->id)
            ->with('success', 'Đã ghi nhận dữ liệu E-Invoices thành công.');
    }

    public function show(Request $request, int $eInvoice): Response
    {
        $this->authorize('viewAny', Invoice::class);

        return Inertia::render('tenant/e-invoices/Show', [
            'batch' => $this->eInvoiceService->showBatch($request->user(), $eInvoice),
        ]);
    }
}