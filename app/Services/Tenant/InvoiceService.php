<?php

namespace App\Services\Tenant;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly CodeSequenceService $codeSequenceService,
        private readonly OrderInventoryService $orderInventoryService,
        private readonly StockPolicyConfigService $stockPolicyConfigService,
    )
    {
    }

    public function list(User $user)
    {
        return Invoice::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['customer', 'order', 'items'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function create(User $user, array $data): Invoice
    {
        return DB::transaction(function () use ($user, $data): Invoice {
            $payload = $data;
            $customerId = $this->resolveCustomerId($user, $data);
            $shouldCreateOrder = (bool) ($payload['create_order_from_invoice'] ?? false);
            $targetOrderId = Arr::get($payload, 'order_id');

            $subtotal = (float) $data['subtotal'];
            $taxRate = (float) ($data['tax_rate'] ?? 0);
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $total = round($subtotal + $taxAmount, 2);

            $parent = null;
            $rootInvoiceId = null;
            $version = 1;

            if (! empty($payload['parent_invoice_id'])) {
                $parent = Invoice::query()->findOrFail($payload['parent_invoice_id']);
                $this->guardTenant($user, $parent);
                $rootInvoiceId = $parent->root_invoice_id ?: $parent->id;
                $version = ((int) $parent->version) + 1;

                if ($parent->status !== 'ISSUED') {
                    throw ValidationException::withMessages([
                        'parent_invoice_id' => 'Chỉ có thể lập hóa đơn thay thế từ hóa đơn đang phát hành.',
                    ]);
                }

                if (is_numeric($parent->order_id) && (int) $parent->order_id > 0 && (! is_numeric($targetOrderId) || (int) $targetOrderId <= 0)) {
                    $targetOrderId = (int) $parent->order_id;
                }

                $rootCode = $this->resolveRootInvoiceCode($parent);
                $payload['change_reason'] = $shouldCreateOrder
                    ? 'Cập nhật từ hóa đơn '.$rootCode
                    : $this->appendNoStockEffectReason((string) Arr::get($payload, 'change_reason', ''));
            }

            $invoice = Invoice::query()->create([
                ...Arr::except($payload, ['items', 'parent_invoice_id']),
                'order_id' => is_numeric($targetOrderId) && (int) $targetOrderId > 0 ? (int) $targetOrderId : null,
                'customer_id' => $customerId,
                'tenant_id' => $user->tenant_id,
                'code' => $this->nextInvoiceCode($user, $parent, $version),
                'status' => 'ISSUED',
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'root_invoice_id' => $rootInvoiceId,
                'parent_invoice_id' => $parent?->id,
                'version' => $version,
                'created_by' => $user->id,
            ]);

            foreach ($payload['items'] as $item) {
                $quantity = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                $discount = (float) ($item['discount'] ?? 0);
                $itemTaxRate = (float) ($item['tax_rate'] ?? 0);
                $baseTotal = max(0, ($quantity * $price) - $discount);
                $itemTaxAmount = round($baseTotal * ($itemTaxRate / 100), 2);

                $invoice->items()->create([
                    ...Arr::except($item, ['id']),
                    'tax_amount' => $itemTaxAmount,
                    'total' => round($baseTotal + $itemTaxAmount, 2),
                ]);
            }

            if ($parent) {
                $parent->status = 'REPLACED';
                $parent->change_reason = 'Invoice replaced by '.$invoice->code;
                $parent->save();
            }

            if ($parent && $shouldCreateOrder && is_numeric($targetOrderId) && (int) $targetOrderId > 0) {
                $linkedOrder = Order::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->whereKey((int) $targetOrderId)
                    ->with('items')
                    ->first();

                if ($linkedOrder) {
                    $this->syncOrderFromReplacementInvoice($user, $linkedOrder, $invoice, $payload['items'] ?? [], $this->resolveRootInvoiceCode($parent));
                }
            }

            if ($shouldCreateOrder && empty($payload['order_id'])) {
                $order = $this->createOrderFromInvoice($user, $invoice);

                $invoice->order_id = $order->id;
                $invoice->save();
            }

            if (is_numeric($invoice->order_id) && (int) $invoice->order_id > 0) {
                Order::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->whereKey((int) $invoice->order_id)
                    ->update(['invoice_id' => $invoice->id]);
            }

            return $invoice->load(['items', 'customer', 'order']);
        });
    }

    private function nextInvoiceCode(User $user, ?Invoice $parent, int $version): string
    {
        if (! $parent) {
            return $this->codeSequenceService->nextCode($user, 'INVOICE');
        }

        $rootCode = $this->resolveRootInvoiceCode($parent);
        $baseCode = preg_replace('/\.\d{3}$/', '', $rootCode) ?: $rootCode;
        $revision = max(1, $version - 1);

        do {
            $candidate = $baseCode.'.'.str_pad((string) $revision, 3, '0', STR_PAD_LEFT);
            $exists = Invoice::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('code', $candidate)
                ->exists();
            $revision++;
        } while ($exists);

        return $candidate;
    }

    private function resolveRootInvoiceCode(Invoice $parent): string
    {
        if (is_numeric($parent->root_invoice_id) && (int) $parent->root_invoice_id > 0) {
            $rootCode = Invoice::query()->whereKey((int) $parent->root_invoice_id)->value('code');
            if (is_string($rootCode) && trim($rootCode) !== '') {
                return trim($rootCode);
            }
        }

        return (string) $parent->code;
    }

    private function appendNoStockEffectReason(string $currentReason): string
    {
        $message = 'Hóa đơn này không có tác dụng làm thay đổi tồn kho thực tế';
        $trimmed = trim($currentReason);

        if ($trimmed === '') {
            return $message;
        }

        if (str_contains(mb_strtolower($trimmed), mb_strtolower($message))) {
            return $trimmed;
        }

        return $trimmed.' | '.$message;
    }

    private function syncOrderFromReplacementInvoice(User $user, Order $order, Invoice $invoice, array $invoiceItems, string $rootInvoiceCode): void
    {
        $previousRequiredByVariant = $order->items
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => (int) $rows->sum(fn ($item) => max(1, (int) ($item->quantity ?? 0)) * max(1, (int) ($item->conversion_rate ?? 1))))
            ->all();

        $order->items()->delete();

        foreach ($invoiceItems as $item) {
            $quantity = max(1, (int) round((float) ($item['quantity'] ?? 0)));
            $price = max(0, (float) ($item['price'] ?? 0));
            $discount = max(0, (float) ($item['discount'] ?? 0));
            $taxRate = max(0, (float) ($item['tax_rate'] ?? 0));
            $baseTotal = max(0, ($quantity * $price) - $discount);
            $taxAmount = round($baseTotal * ($taxRate / 100), 2);

            $order->items()->create([
                'product_variant_id' => (int) ($item['product_variant_id'] ?? 0),
                'unit_name' => (string) ($item['unit_name'] ?? 'Cái'),
                'conversion_rate' => 1,
                'quantity' => $quantity,
                'price' => $price,
                'discount' => $discount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => round($baseTotal + $taxAmount, 2),
            ]);
        }

        $order->load('items');
        $order->invoice_id = $invoice->id;
        $order->total_amount = (float) $invoice->subtotal;
        $order->final_amount = (float) $invoice->total_amount;
        $order->debt_amount = max(0, (float) $order->final_amount - (float) ($order->paid_amount ?? 0));
        $order->revision_reason = 'Cập nhật từ hóa đơn '.$rootInvoiceCode;
        $order->save();

        if ($this->shouldDeductStock((string) $order->status)) {
            $this->orderInventoryService->reconcileForOrderUpdate(
                user: $user,
                order: $order,
                previousRequiredByVariant: $previousRequiredByVariant,
                allowNegativeStock: $this->stockPolicyConfigService->allowNegativeStock($user),
                note: 'Điều chỉnh theo hóa đơn thay thế '.$invoice->code,
            );
        }
    }

    private function shouldDeductStock(string $status): bool
    {
        return in_array(strtoupper($status), ['CONFIRMED', 'PROCESSING', 'COMPLETED'], true);
    }

    public function update(User $user, Invoice $invoice, array $data): Invoice
    {
        $this->guardTenant($user, $invoice);

        if (in_array($invoice->status, ['CANCELLED', 'REPLACED'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Hóa đơn đã hủy hoặc đã bị thay thế thì không thể cập nhật lại.',
            ]);
        }

        $nextStatus = strtoupper((string) ($data['status'] ?? ''));
        if (! in_array($nextStatus, ['ISSUED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hóa đơn đang phát hành chỉ có thể hủy trực tiếp. Muốn thay đổi nội dung, hãy lập hóa đơn thay thế.',
            ]);
        }

        $invoice->fill(Arr::only($data, ['status', 'change_reason']));
        $invoice->save();

        return $invoice->refresh()->load(['items', 'customer', 'order']);
    }

    public function delete(User $user, Invoice $invoice): void
    {
        $this->guardTenant($user, $invoice);

        if (strtoupper((string) $invoice->status) !== 'CANCELLED') {
            throw ValidationException::withMessages([
                'invoice' => 'Chỉ được xóa mềm hóa đơn đã hủy.',
            ]);
        }

        $invoice->delete();
    }

    private function guardTenant(User $user, Invoice $invoice): void
    {
        if ($invoice->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice does not belong to this tenant.',
            ]);
        }
    }

    private function resolveCustomerId(User $user, array $data): ?int
    {
        // Respect explicit customer input from caller: null means no customer selected.
        if (Arr::has($data, 'customer_id')) {
            $providedCustomerId = Arr::get($data, 'customer_id');

            if (is_numeric($providedCustomerId) && (int) $providedCustomerId > 0) {
                return (int) $providedCustomerId;
            }

            return null;
        }

        $orderId = Arr::get($data, 'order_id');
        if (is_numeric($orderId) && (int) $orderId > 0) {
            $orderCustomerId = Order::query()
                ->where('tenant_id', $user->tenant_id)
                ->whereKey((int) $orderId)
                ->value('customer_id');

            if (is_numeric($orderCustomerId) && (int) $orderCustomerId > 0) {
                return (int) $orderCustomerId;
            }
        }

        $guestCustomerId = Customer::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn(DB::raw('LOWER(name)'), ['khach le', 'khách lẻ', 'guest'])
            ->orderBy('id')
            ->value('id');

        if (is_numeric($guestCustomerId) && (int) $guestCustomerId > 0) {
            return (int) $guestCustomerId;
        }

        return null;
    }

    private function createOrderFromInvoice(User $user, Invoice $invoice): Order
    {
        $warehouseId = Warehouse::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('id')
            ->value('id');

        if (! is_numeric($warehouseId) || (int) $warehouseId <= 0) {
            throw ValidationException::withMessages([
                'create_order_from_invoice' => 'Không thể tạo đơn hàng từ hóa đơn vì chưa có kho nào trong hệ thống.',
            ]);
        }

        $order = Order::query()->create([
            'tenant_id' => $user->tenant_id,
            'code' => $this->codeSequenceService->nextCode($user, 'ORDER'),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'user_id' => $user->id,
            'warehouse_id' => (int) $warehouseId,
            'type' => 'RETAIL',
            'status' => 'COMPLETED',
            'is_locked' => false,
            'total_amount' => (float) $invoice->subtotal,
            'discount' => 0,
            'surcharge' => 0,
            'final_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->total_amount,
            'debt_amount' => 0,
            'note' => 'Tạo tự động từ hóa đơn '.$invoice->code,
            'order_date' => $invoice->sale_at ?? now(),
            'completed_at' => now(),
        ]);

        foreach ($invoice->items as $item) {
            $quantity = max(1, (int) round((float) ($item->quantity ?? 0)));
            $price = (float) ($item->price ?? 0);
            $discount = max(0, (float) ($item->discount ?? 0));
            $taxRate = max(0, (float) ($item->tax_rate ?? 0));
            $baseTotal = max(0, ($quantity * $price) - $discount);
            $taxAmount = round($baseTotal * ($taxRate / 100), 2);

            $order->items()->create([
                'product_variant_id' => $item->product_variant_id,
                'unit_name' => $item->unit_name ?: 'Cái',
                'conversion_rate' => 1,
                'quantity' => $quantity,
                'price' => $price,
                'discount' => $discount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => round($baseTotal + $taxAmount, 2),
            ]);
        }

        return $order;
    }
}
