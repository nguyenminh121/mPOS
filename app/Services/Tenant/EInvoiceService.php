<?php

namespace App\Services\Tenant;

use App\Models\Invoice;
use App\Models\EInvoiceBatch;
use App\Models\EInvoiceDetail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EInvoiceService
{
    public function listBatches(User $user)
    {
        return EInvoiceBatch::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['creator:id,name'])
            ->withCount('details')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    public function showBatch(User $user, int $batchId): EInvoiceBatch
    {
        return EInvoiceBatch::query()
            ->where('tenant_id', $user->tenant_id)
            ->with([
                'creator:id,name',
                'details' => fn ($query) => $query
                    ->with([
                        'invoice:id,code,status,e_invoice_status,e_invoiced_at',
                        'order:id,code,status',
                    ])
                    ->orderBy('id'),
            ])
            ->findOrFail($batchId);
    }

    public function exportInvoices(User $user, array $invoiceRefs, string $mode = 'BATCH', ?string $provider = null): EInvoiceBatch
    {
        $normalizedRefs = collect($invoiceRefs)
            ->map(fn ($ref) => trim((string) $ref))
            ->filter(fn ($ref) => $ref !== '')
            ->unique()
            ->values();

        if ($normalizedRefs->isEmpty()) {
            throw ValidationException::withMessages([
                'invoice_refs' => 'Vui lòng chọn ít nhất 1 hóa đơn để đồng bộ E-Invoices.',
            ]);
        }

        $safeMode = strtoupper(trim($mode));
        if (! in_array($safeMode, ['BATCH', 'REALTIME'], true)) {
            $safeMode = 'BATCH';
        }

        return DB::transaction(function () use ($user, $normalizedRefs, $safeMode, $provider): EInvoiceBatch {
            $invoices = Invoice::query()
                ->where('tenant_id', $user->tenant_id)
                ->whereIn('code', $normalizedRefs->all())
                ->with(['order:id,code,status', 'customer:id,name'])
                ->orderBy('id')
                ->get();

            if ($invoices->isEmpty()) {
                throw ValidationException::withMessages([
                    'invoice_refs' => 'Không tìm thấy hóa đơn hợp lệ thuộc tenant hiện tại.',
                ]);
            }

            $batch = EInvoiceBatch::query()->create([
                'tenant_id' => $user->tenant_id,
                'code' => $this->nextBatchCode(),
                'export_date' => now()->toDateString(),
                'mode' => $safeMode,
                'status' => 'PROCESSING',
                'provider' => $provider ? trim($provider) : 'MANUAL',
                'created_by' => $user->id,
                'payload_snapshot' => json_encode([
                    'invoice_refs' => $normalizedRefs->all(),
                    'requested_at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $summary = $this->exportInvoiceCollection($batch, $invoices);

            $batch->status = $summary['has_failure'] ? 'FAILED' : 'EXPORTED';
            $batch->total_invoices = $summary['total_invoices'];
            $batch->total_amount = $summary['total_amount'];
            $batch->exported_at = now();
            $batch->response_snapshot = json_encode([
                'exported' => $summary['exported_count'],
                'skipped' => $summary['skipped_count'],
                'failed' => $summary['failed_count'],
            ], JSON_UNESCAPED_UNICODE);
            $batch->save();

            return $batch->refresh();
        });
    }

    public function candidateInvoices(User $user): Collection
    {
        return Invoice::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'ISSUED')
            ->where('e_invoice_status', 'NOT_EXPORTED')
            ->with(['order:id,code,status', 'customer:id,name'])
            ->latest('id')
            ->limit(200)
            ->get();
    }

    private function exportInvoiceCollection(EInvoiceBatch $batch, Collection $invoices): array
    {
        $totalAmount = 0.0;
        $exportedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($invoices as $invoice) {
            $status = strtoupper((string) $invoice->status);
            $taxStatus = strtoupper((string) ($invoice->e_invoice_status ?? 'NOT_EXPORTED'));

            if ($status !== 'ISSUED') {
                $this->createDetail($batch, $invoice, 'SKIPPED', 'Hóa đơn không ở trạng thái ISSUED.');
                $skippedCount++;
                continue;
            }

            if ($taxStatus === 'EXPORTED') {
                $this->createDetail($batch, $invoice, 'SKIPPED', 'Hóa đơn đã được đồng bộ E-Invoices trước đó.');
                $skippedCount++;
                continue;
            }

            try {
                $invoice->e_invoice_status = 'EXPORTED';
                $invoice->e_invoiced_at = now();
                $invoice->save();

                $this->createDetail($batch, $invoice, 'EXPORTED');

                $totalAmount += (float) ($invoice->total_amount ?? 0);
                $exportedCount++;
            } catch (\Throwable $e) {
                $this->createDetail($batch, $invoice, 'FAILED', $e->getMessage());
                $failedCount++;
            }
        }

        return [
            'total_invoices' => $invoices->count(),
            'total_amount' => round($totalAmount, 2),
            'exported_count' => $exportedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'has_failure' => $failedCount > 0,
        ];
    }

    private function createDetail(EInvoiceBatch $batch, Invoice $invoice, string $exportedStatus, ?string $errorMessage = null): EInvoiceDetail
    {
        return EInvoiceDetail::query()->create([
            'tenant_id' => $batch->tenant_id,
            'e_invoice_batch_id' => $batch->id,
            'invoice_id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'invoice_code' => (string) $invoice->code,
            'order_code' => $invoice->order?->code,
            'customer_name' => $invoice->customer?->name,
            'invoice_total' => (float) ($invoice->total_amount ?? 0),
            'invoice_sale_at' => $invoice->sale_at,
            'exported_status' => $exportedStatus,
            'provider_reference' => $exportedStatus === 'EXPORTED'
                ? 'MANUAL-'.strtoupper(substr(md5((string) $invoice->id.'|'.(string) now()), 0, 12))
                : null,
            'error_message' => $errorMessage,
            'payload_snapshot' => json_encode([
                'invoice_id' => $invoice->id,
                'invoice_code' => $invoice->code,
                'total_amount' => (float) ($invoice->total_amount ?? 0),
            ], JSON_UNESCAPED_UNICODE),
            'response_snapshot' => $exportedStatus === 'EXPORTED'
                ? json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE)
                : null,
        ]);
    }

    private function nextBatchCode(): string
    {
        return 'EIV-'.now()->format('Ymd-His').'-'.strtoupper(substr((string) uniqid('', true), -4));
    }
}
