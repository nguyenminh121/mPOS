<?php

namespace App\Services\Tenant;

use App\Models\PosShift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceUsageConfigService $invoiceUsageConfigService,
    )
    {
    }

    public function createSale(User $user, array $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $createReceivable = (bool) ($data['create_receivable'] ?? false);
            $paidAmount = max(0, (float) ($data['paid_amount'] ?? 0));
            $useInvoiceOnSale = $this->invoiceUsageConfigService->useInvoiceOnSale($user);
            $invoiceMode = $this->invoiceUsageConfigService->getInvoiceMode($user);
            $orderType = strtoupper((string) ($data['type'] ?? 'RETAIL'));

            $payload = [
                ...$data,
                'type' => $orderType,
                'status' => $orderType === 'RETAIL'
                    ? 'COMPLETED'
                    : ($createReceivable ? 'CONFIRMED' : 'COMPLETED'),
                'paid_amount' => $createReceivable ? 0 : $paidAmount,
            ];

            unset($payload['create_receivable']);

            $order = $this->orderService->create($user, $payload);

            if (! $useInvoiceOnSale) {
                return $order;
            }

            $shouldIssueRealtimeInvoice = match ($invoiceMode) {
                InvoiceUsageConfigService::MODE_BATCH =>
                    $order->customer_id !== null && strtoupper((string) ($order->type ?? '')) === 'WHOLESALE',
                InvoiceUsageConfigService::MODE_STANDARD,
                InvoiceUsageConfigService::MODE_POS => true,
                default => true,
            };

            if (! $shouldIssueRealtimeInvoice) {
                return $order->refresh();
            }

            $order->loadMissing('items.productVariant');

            $invoice = $this->invoiceService->create($user, [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'subtotal' => (float) $order->total_amount,
                'tax_rate' => 0,
                'total_amount' => (float) $order->final_amount,
                'sale_at' => $order->order_date ?? now(),
                'items' => $order->items->map(fn ($item) => [
                    'product_variant_id' => (int) $item->product_variant_id,
                    'product_name' => $item->productVariant?->name ?? ('Variant #'.$item->product_variant_id),
                    'unit_name' => $item->unit_name,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'discount' => (float) ($item->discount ?? 0),
                    'tax_rate' => (float) ($item->tax_rate ?? 0),
                ])->values()->all(),
            ]);

            $order->invoice_id = $invoice->id;
            $order->save();

            return $order->refresh();
        });
    }

    public function openShift(User $user, array $data): PosShift
    {
        $active = PosShift::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['OPEN', 'SUSPENDED'])
            ->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'shift' => 'An open/suspended POS shift already exists for this user.',
            ]);
        }

        return PosShift::query()->create([
            'tenant_id' => $user->tenant_id,
            'warehouse_id' => $data['warehouse_id'],
            'user_id' => $user->id,
            'status' => 'OPEN',
            'opening_cash' => $data['opening_cash'],
            'opened_at' => now(),
            'note' => $data['note'] ?? null,
        ]);
    }

    public function closeShift(User $user, PosShift $shift, array $data): PosShift
    {
        if ($shift->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'shift' => 'POS shift does not belong to this tenant.',
            ]);
        }

        if (! in_array($shift->status, ['OPEN', 'SUSPENDED'], true)) {
            throw ValidationException::withMessages([
                'shift' => 'Only OPEN/SUSPENDED shifts can be closed.',
            ]);
        }

        $closingCash = (float) ($data['closing_cash'] ?? 0);
        $expectedCash = (float) ($data['expected_cash'] ?? 0);

        $shift->fill([
            'status' => 'CLOSED',
            'closing_cash' => $closingCash,
            'expected_cash' => $expectedCash,
            'cash_difference' => round($closingCash - $expectedCash, 2),
            'closed_at' => now(),
            'closed_by' => $user->id,
            'note' => $data['note'] ?? $shift->note,
        ]);

        $shift->save();

        return $shift->refresh();
    }

    public function listSales(User $user)
    {
        return $this->orderService->list($user, 'RETAIL');
    }
}
