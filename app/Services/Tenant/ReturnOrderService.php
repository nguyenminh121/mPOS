<?php

namespace App\Services\Tenant;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnOrder;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnOrderService
{
    public function __construct(
        private readonly CodeSequenceService $codeSequenceService,
        private readonly InvoiceService $invoiceService,
    ) {
    }

    public function list(User $user)
    {
        return ReturnOrder::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['order', 'customer', 'items'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function create(User $user, array $data): ReturnOrder
    {
        return DB::transaction(function () use ($user, $data): ReturnOrder {
            $order = Order::query()
                ->where('tenant_id', $user->tenant_id)
                ->findOrFail((int) ($data['order_id'] ?? 0));

            $order->loadMissing(['items', 'surcharges']);
            $returnedQtyByOrderItem = $this->getReturnedQtyByOrderItem((int) $order->id);

            $this->guardOrderCanBeReturned($order, $returnedQtyByOrderItem);

            $status = $this->normalizeStatus((string) ($data['status'] ?? 'PENDING'));
            $warehouseId = (int) ($data['warehouse_id'] ?? $order->warehouse_id ?? 0);
            abort_if($warehouseId <= 0, 422, 'Không xác định được kho nhập trả hàng.');

            $items = $this->normalizeAndValidateReturnItems($order, $data['items'] ?? [], $returnedQtyByOrderItem);

            $isFullReturnAfterCreate = $this->isOrderFullyReturnedAfterRequest($order, $items, $returnedQtyByOrderItem);

            $totalAmount = $items->sum(fn (array $item) => (float) ($item['refund_amount'] ?? 0));

            $returnOrder = ReturnOrder::query()->create([
                ...Arr::except($data, ['items']),
                'tenant_id' => $user->tenant_id,
                'code' => $this->codeSequenceService->nextCode($user, 'RETURN'),
                'order_id' => $order->id,
                'customer_id' => $data['customer_id'] ?? $order->customer_id,
                'warehouse_id' => $warehouseId,
                'status' => $status,
                'total_amount' => round($totalAmount, 2),
                'return_date' => now(),
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $returnOrder->items()->create(Arr::except($item, ['id']));

                OrderItem::query()
                    ->whereKey((int) $item['order_item_id'])
                    ->increment('returned_quantity', (int) $item['quantity']);
            }

            $replacementInvoice = $this->replaceInvoiceForReturn($user, $order, $returnOrder->code, $items, $returnedQtyByOrderItem);
            if ($replacementInvoice !== null) {
                $returnOrder->note = trim((string) ($returnOrder->note ?? '') . ' | Invoice replaced by ' . $replacementInvoice->code);
                $returnOrder->save();
            }

            if ($this->shouldRestoreStockOnCreate($status)) {
                $returnOrder->load('items');
                $this->restoreInventoryFromReturnOrder($user, $returnOrder);
            }

            if ($isFullReturnAfterCreate) {
                $order->is_locked = true;
                $order->locked_at = now();
                $order->locked_by = $user->id;
                $order->locked_reason = 'FULL_RETURN_REACHED';
                $order->save();
            }

            return $returnOrder->load(['order', 'customer', 'items']);
        });
    }

    public function update(User $user, ReturnOrder $returnOrder, array $data): ReturnOrder
    {
        $this->guardTenant($user, $returnOrder);
        $previousStatus = strtoupper((string) $returnOrder->status);
        $nextStatus = $this->normalizeStatus((string) ($data['status'] ?? $returnOrder->status));

        $returnOrder->fill([
            ...Arr::only($data, ['refund_method', 'reason', 'note']),
            'status' => $nextStatus,
        ]);
        $returnOrder->save();

        if ($this->shouldRestoreStockOnStatusTransition($previousStatus, $nextStatus)) {
            $returnOrder->loadMissing('items');
            $this->restoreInventoryFromReturnOrder($user, $returnOrder);
        }

        return $returnOrder->refresh()->load(['order', 'customer', 'items']);
    }

    public function delete(User $user, ReturnOrder $returnOrder): void
    {
        $this->guardTenant($user, $returnOrder);
        $returnOrder->delete();
    }

    private function guardTenant(User $user, ReturnOrder $returnOrder): void
    {
        if ($returnOrder->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'return_order' => 'Return order does not belong to this tenant.',
            ]);
        }
    }

    private function guardOrderCanBeReturned(Order $order, Collection $returnedQtyByOrderItem): void
    {
        if (strtoupper((string) $order->status) !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'order_id' => 'Chỉ cho phép tạo trả hàng cho đơn đã hoàn thành.',
            ]);
        }

        $order->loadMissing('items');
        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages([
                'order_id' => 'Đơn hàng không có chi tiết sản phẩm để thực hiện trả hàng.',
            ]);
        }

        $fullyReturned = $order->items->every(function (OrderItem $item) use ($returnedQtyByOrderItem): bool {
            $soldQty = max(0, (int) ($item->quantity ?? 0));
            $returnedQty = max(0, (int) ($returnedQtyByOrderItem->get((int) $item->id, 0)));

            return $returnedQty >= $soldQty;
        });

        if ($fullyReturned) {
            throw ValidationException::withMessages([
                'order_id' => 'Đơn hàng đã được trả đủ 100%, không thể tạo thêm phiếu trả.',
            ]);
        }
    }

    private function replaceInvoiceForReturn(User $user, Order $order, string $returnCode, Collection $currentReturnItems, Collection $returnedQtyByOrderItem): ?Invoice
    {
        if (! is_numeric($order->invoice_id) || (int) $order->invoice_id <= 0) {
            return null;
        }

        $invoice = Invoice::query()
            ->where('tenant_id', $user->tenant_id)
            ->find((int) $order->invoice_id);

        if (! $invoice) {
            return null;
        }

        $order->loadMissing('items');
        $invoice->loadMissing('items');

        $returningQtyByOrderItem = $currentReturnItems
            ->groupBy(fn (array $item) => (int) ($item['order_item_id'] ?? 0))
            ->map(fn (Collection $rows): int => (int) $rows->sum(fn (array $row) => (int) ($row['quantity'] ?? 0)));

        $invoiceItemByVariant = $invoice->items
            ->groupBy('product_variant_id')
            ->map(fn (Collection $rows) => $rows->first());

        $replacementItems = $order->items
            ->map(function (OrderItem $orderItem) use ($returnedQtyByOrderItem, $returningQtyByOrderItem, $invoiceItemByVariant): ?array {
                $itemId = (int) $orderItem->id;
                $soldQty = max(0, (int) ($orderItem->quantity ?? 0));
                $alreadyReturned = max(0, (int) ($returnedQtyByOrderItem->get($itemId, 0)));
                $returningNow = max(0, (int) ($returningQtyByOrderItem->get($itemId, 0)));
                $remainingQty = max(0, $soldQty - $alreadyReturned - $returningNow);

                if ($remainingQty <= 0) {
                    return null;
                }

                $sourceInvoiceItem = $invoiceItemByVariant->get((int) $orderItem->product_variant_id);
                $price = (float) ($sourceInvoiceItem?->price ?? $orderItem->price ?? 0);
                $discount = (float) ($sourceInvoiceItem?->discount ?? $orderItem->discount ?? 0);
                $taxRate = (float) ($sourceInvoiceItem?->tax_rate ?? $orderItem->tax_rate ?? 0);
                $originalQty = max(1.0, (float) ($sourceInvoiceItem?->quantity ?? $soldQty ?: 1));
                $discountPerUnit = $discount > 0 ? ($discount / $originalQty) : 0.0;

                return [
                    'product_variant_id' => (int) $orderItem->product_variant_id,
                    'product_name' => (string) ($sourceInvoiceItem?->product_name ?? ('Variant #'.(int) $orderItem->product_variant_id)),
                    'unit_name' => (string) ($sourceInvoiceItem?->unit_name ?? $orderItem->unit_name ?? 'Cái'),
                    'quantity' => (float) $remainingQty,
                    'price' => $price,
                    'discount' => round($discountPerUnit * $remainingQty, 2),
                    'tax_rate' => $taxRate,
                ];
            })
            ->filter()
            ->values();

        $subtotal = round((float) $replacementItems->sum(function (array $item): float {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);

            return max(0, ($quantity * $price) - $discount);
        }), 2);

        $newInvoice = $this->invoiceService->create($user, [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'parent_invoice_id' => $invoice->id,
            'create_order_from_invoice' => false,
            'change_reason' => 'Điều chỉnh theo phiếu trả hàng '.$returnCode,
            'subtotal' => $subtotal,
            'tax_rate' => 0,
            'sale_at' => $order->order_date ?? now(),
            'items' => $replacementItems->all(),
        ]);

        return $newInvoice;
    }

    private function getReturnedQtyByOrderItem(int $orderId): Collection
    {
        return DB::table('return_order_items')
            ->join('return_orders', 'return_orders.id', '=', 'return_order_items.return_order_id')
            ->where('return_orders.order_id', $orderId)
            ->whereIn('return_orders.status', ['PENDING', 'APPROVED', 'COMPLETED'])
            ->groupBy('return_order_items.order_item_id')
            ->selectRaw('return_order_items.order_item_id as order_item_id, SUM(return_order_items.quantity) as total_quantity')
            ->pluck('total_quantity', 'order_item_id')
            ->map(fn ($value) => (int) $value);
    }

    private function normalizeAndValidateReturnItems(Order $order, array $rawItems, Collection $returnedQtyByOrderItem): Collection
    {
        if (count($rawItems) === 0) {
            throw ValidationException::withMessages([
                'items' => 'Phiếu trả hàng phải có ít nhất một dòng sản phẩm.',
            ]);
        }

        $orderItems = $order->items->keyBy(fn (OrderItem $item) => (int) $item->id);
        $orderItemsByVariant = $order->items->groupBy(fn (OrderItem $item) => (int) $item->product_variant_id);
        $requestedByOrderItem = [];

        $normalizedItems = collect($rawItems)->map(function (array $item) use ($orderItems, $orderItemsByVariant, $returnedQtyByOrderItem, &$requestedByOrderItem): array {
            $requestedOrderItemId = isset($item['order_item_id']) ? (int) $item['order_item_id'] : 0;
            $requestedVariantId = (int) ($item['product_variant_id'] ?? 0);

            $orderItem = null;
            if ($requestedOrderItemId > 0) {
                $orderItem = $orderItems->get($requestedOrderItemId);
            } elseif ($requestedVariantId > 0) {
                $candidates = $orderItemsByVariant->get($requestedVariantId, collect());
                if ($candidates->count() === 1) {
                    $orderItem = $candidates->first();
                }
            }

            if (! $orderItem) {
                throw ValidationException::withMessages([
                    'items' => 'Không xác định được dòng hàng gốc để trả. Vui lòng chọn đúng sản phẩm từ đơn nguồn.',
                ]);
            }

            $orderItemId = (int) $orderItem->id;
            $orderItemVariantId = (int) $orderItem->product_variant_id;

            if ($requestedVariantId > 0 && $requestedVariantId !== $orderItemVariantId) {
                throw ValidationException::withMessages([
                    'items' => 'Sản phẩm trả không khớp với dòng sản phẩm của đơn hàng gốc.',
                ]);
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 0));
            $price = (float) ($item['price'] ?? $orderItem->price ?? 0);
            $refundAmount = (float) ($item['refund_amount'] ?? ($price * $quantity));

            $alreadyReturned = max(0, (int) ($returnedQtyByOrderItem->get($orderItemId, 0)));
            $requestingNow = max(0, (int) ($requestedByOrderItem[$orderItemId] ?? 0));
            $soldQty = max(0, (int) ($orderItem->quantity ?? 0));
            $remainingQty = $soldQty - $alreadyReturned - $requestingNow;

            if ($remainingQty <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Một hoặc nhiều sản phẩm đã trả đủ số lượng, không thể trả thêm.',
                ]);
            }

            if ($quantity > $remainingQty) {
                throw ValidationException::withMessages([
                    'items' => 'Số lượng trả vượt quá số lượng còn lại có thể trả của đơn gốc.',
                ]);
            }

            $requestedByOrderItem[$orderItemId] = $requestingNow + $quantity;

            return [
                ...$item,
                'order_item_id' => $orderItemId,
                'product_variant_id' => $orderItemVariantId,
                'quantity' => $quantity,
                'price' => $price,
                'refund_amount' => round(max(0, $refundAmount), 2),
            ];
        })->values();

        return $normalizedItems;
    }

    private function isOrderFullyReturnedAfterRequest(Order $order, Collection $items, Collection $returnedQtyByOrderItem): bool
    {
        $requestedByOrderItem = $items
            ->groupBy(fn (array $item) => (int) ($item['order_item_id'] ?? 0))
            ->map(fn (Collection $rows) => (int) $rows->sum(fn (array $row) => (int) ($row['quantity'] ?? 0)));

        return $order->items->every(function (OrderItem $orderItem) use ($requestedByOrderItem, $returnedQtyByOrderItem): bool {
            $itemId = (int) $orderItem->id;
            $soldQty = max(0, (int) ($orderItem->quantity ?? 0));
            $alreadyReturned = max(0, (int) ($returnedQtyByOrderItem->get($itemId, 0)));
            $requestingNow = max(0, (int) ($requestedByOrderItem->get($itemId, 0)));

            return ($alreadyReturned + $requestingNow) >= $soldQty;
        });
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));
        $allowed = ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED', 'CANCELLED'];

        return in_array($normalized, $allowed, true) ? $normalized : 'PENDING';
    }

    private function shouldRestoreStockOnCreate(string $status): bool
    {
        return in_array($status, ['APPROVED', 'COMPLETED'], true);
    }

    private function shouldRestoreStockOnStatusTransition(string $from, string $to): bool
    {
        if (! in_array($to, ['APPROVED', 'COMPLETED'], true)) {
            return false;
        }

        return ! in_array($from, ['APPROVED', 'COMPLETED'], true);
    }

    private function restoreInventoryFromReturnOrder(User $user, ReturnOrder $returnOrder): void
    {
        $warehouseId = (int) ($returnOrder->warehouse_id ?? 0);
        abort_if($warehouseId <= 0, 422, 'Không xác định được kho nhập trả hàng.');

        $requiredByVariant = $returnOrder->items
            ->groupBy('product_variant_id')
            ->map(function (Collection $rows): int {
                return (int) $rows->sum(function ($item) {
                    return max(1, (int) ($item->quantity ?? 0));
                });
            })
            ->filter(fn (int $qty) => $qty > 0);

        foreach ($requiredByVariant as $variantId => $restockQty) {
            $stock = Stock::query()->firstOrCreate(
                [
                    'product_variant_id' => (int) $variantId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'warehouse_location_id' => null,
                    'quantity' => 0,
                ],
            );

            $newBalance = (int) $stock->quantity + (int) $restockQty;
            $stock->quantity = $newBalance;
            $stock->save();

            StockMovement::query()->create([
                'product_variant_id' => (int) $variantId,
                'warehouse_id' => $warehouseId,
                'batch_id' => null,
                'movement_type' => 'ADJUST_IN',
                'quantity' => (int) $restockQty,
                'balance_after' => $newBalance,
                'cost_price_at_move' => null,
                'order_id' => $returnOrder->order_id ? (int) $returnOrder->order_id : null,
                'import_item_id' => null,
                'stock_take_id' => null,
                'note' => 'Nhập kho từ phiếu trả hàng '.$returnOrder->code,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }
    }
}
