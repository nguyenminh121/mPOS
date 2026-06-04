<?php

namespace App\Services\Tenant;

use App\Domain\Lifecycle\Data\TransitionContext;
use App\Domain\Lifecycle\Orders\OrderStateMachine;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly CodeSequenceService $codeSequenceService,
        private readonly StockPolicyConfigService $stockPolicyConfigService,
        private readonly OrderInventoryService $orderInventoryService,
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceUsageConfigService $invoiceUsageConfigService,
        private readonly CustomerDebtService $customerDebtService,
        private readonly OrderStateMachine $orderStateMachine,
    ) {
    }

    public function list(User $user, ?string $type = null, int $perPage = 20)
    {
        return Order::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($type, fn ($q) => $q->where('type', strtoupper($type)))
            ->with([
                'customer',
                'user:id,name',
                'warehouse',
                'surcharges',
                'invoice:id,code,e_invoice_status,e_invoiced_at',
                'items.productVariant:id,product_id,name,sku,barcode',
                'items.productVariant.product:id,category_id',
                'items.productVariant.product.category:id,name',
                'returnOrders:id,order_id,code,status,total_amount,return_date,created_at',
            ])
            ->latest()
                ->paginate($perPage)
            ->withQueryString();
    }

    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $orderDiscount = max(0, (float) ($data['discount'] ?? 0));
            $totals = $this->calculateTotals($data['items'] ?? [], $data['surcharges'] ?? [], $orderDiscount);
            $status = strtoupper((string) ($data['status'] ?? 'DRAFT'));
            $paidAmount = max(0, (float) ($data['paid_amount'] ?? 0));
            $paidAmount = min($paidAmount, $totals['final']);
            $debtAmount = max(0, $totals['final'] - $paidAmount);
            $statusChangedAt = now();

            $this->guardPaymentPolicyForOrder(
                customerId: Arr::get($data, 'customer_id'),
                paidAmount: $paidAmount,
                finalAmount: $totals['final'],
            );

            $order = Order::query()->create([
                ...Arr::except($data, ['items', 'surcharges']),
                'tenant_id' => $user->tenant_id,
                'code' => $this->codeSequenceService->nextCode($user, 'ORDER'),
                'user_id' => (int) ($data['seller_id'] ?? $user->id),
                'status' => $status,
                'is_locked' => false,
                'total_amount' => $totals['subtotal'],
                'surcharge' => $totals['surcharge'],
                'final_amount' => $totals['final'],
                'paid_amount' => $paidAmount,
                'debt_amount' => $debtAmount,
                'order_date' => Arr::get($data, 'order_date') ?? now(),
                'completed_at' => $status === 'COMPLETED' ? now() : null,
                'processing_at' => $status === 'PROCESSING' ? $statusChangedAt : null,
                'confirmed_at' => $status === 'CONFIRMED' ? $statusChangedAt : null,
                'cancelled_at' => $status === 'CANCELLED' ? $statusChangedAt : null,
            ]);

            $this->syncItems($order, $data['items'] ?? []);
            $this->syncSurcharges($order, $data['surcharges'] ?? []);

            if ($this->shouldDeductStock((string) $order->status)) {
                $this->orderInventoryService->deductForOrder(
                    user: $user,
                    order: $order,
                    allowNegativeStock: $this->stockPolicyConfigService->allowNegativeStock($user),
                );
            }

            $this->recordFinancialEntries(
                user: $user,
                order: $order,
                paidAmount: $paidAmount,
                debtAmount: $debtAmount,
            );

            $this->customerDebtService->syncForCustomer(
                tenantId: (int) $user->tenant_id,
                customerId: is_numeric($order->customer_id) ? (int) $order->customer_id : null,
            );

            return $order->load(['items', 'surcharges', 'customer']);
        });
    }

    public function update(User $user, Order $order, array $data): Order
    {
        $this->guardMutableTenantOrder($user, $order);

        return DB::transaction(function () use ($user, $order, $data): Order {
            $targetOrder = $order;
            $previousCustomerId = is_numeric($order->customer_id) ? (int) $order->customer_id : null;
            if ($this->shouldCreateOrderRevision($data)) {
                $revisionReason = trim((string) ($data['revision_reason'] ?? ''));
                $targetOrder = $this->createOrderRevision($user, $order, $revisionReason);
            }

            $fromStatus = strtoupper((string) $targetOrder->status);
            $toStatus = strtoupper((string) ($data['status'] ?? $targetOrder->status));
            $statusChanged = $fromStatus !== $toStatus;
            $statusChangedAt = now();
            $hasBusinessFieldChanges = $this->hasBusinessFieldChanges($data);
            $this->guardCompletedOrderTaxExportLock($targetOrder, $fromStatus, $toStatus, $data);
            $allowCompletedContentEdit = $this->canEditCompletedOrderContent(
                user: $user,
                order: $targetOrder,
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                hasBusinessFieldChanges: $hasBusinessFieldChanges,
            );
            $invoiceExportMode = strtoupper((string) ($data['invoice_export_mode'] ?? 'NOW'));
            $previousPaidAmount = (float) ($targetOrder->paid_amount ?? 0);
            $previousDebtAmount = (float) ($targetOrder->debt_amount ?? 0);
            if (! in_array($invoiceExportMode, ['NOW', 'BATCH'], true)) {
                $invoiceExportMode = 'NOW';
            }

            $this->guardOrderStatusTransition($user, $fromStatus, $toStatus);
            $this->guardImmutableFieldsWhenOrderIsProcessing($fromStatus, $data, $allowCompletedContentEdit);

            $orderDiscount = max(0, (float) ($data['discount'] ?? $targetOrder->discount ?? 0));
            $totals = $this->calculateTotals(
                $data['items'] ?? $targetOrder->items->toArray(),
                $data['surcharges'] ?? $targetOrder->surcharges->toArray(),
                $orderDiscount,
            );
            $replaceInvoice = (bool) ($data['replace_invoice'] ?? false);
            if ($allowCompletedContentEdit && $targetOrder->invoice_id) {
                $replaceInvoice = true;
            }
            $revisionReason = trim((string) ($data['revision_reason'] ?? ''));
            $nextPaidAmount = array_key_exists('paid_amount', $data)
                ? max(0, min((float) ($data['paid_amount'] ?? 0), $totals['final']))
                : (float) ($targetOrder->paid_amount ?? 0);

            if ($nextPaidAmount < $previousPaidAmount) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Số tiền đã thanh toán không được nhỏ hơn số đã thu trước đó.',
                ]);
            }

            $nextCustomerId = Arr::get($data, 'customer_id', $targetOrder->customer_id);
            $this->guardPaymentPolicyForOrder(
                customerId: $nextCustomerId,
                paidAmount: $nextPaidAmount,
                finalAmount: $totals['final'],
            );

            $targetOrder->fill([
                ...Arr::except($data, ['items', 'surcharges', 'replace_invoice', 'invoice_export_mode']),
                'status' => $toStatus,
                'total_amount' => $totals['subtotal'],
                'surcharge' => $totals['surcharge'],
                'final_amount' => $totals['final'],
                'paid_amount' => $nextPaidAmount,
                'debt_amount' => max(0, $totals['final'] - $nextPaidAmount),
                'completed_at' => $toStatus === 'COMPLETED' && $statusChanged ? $statusChangedAt : $targetOrder->completed_at,
                'processing_at' => $toStatus === 'PROCESSING' && $statusChanged ? $statusChangedAt : $targetOrder->processing_at,
                'confirmed_at' => $toStatus === 'CONFIRMED' && $statusChanged ? $statusChangedAt : $targetOrder->confirmed_at,
                'cancelled_at' => $toStatus === 'CANCELLED' && $statusChanged ? $statusChangedAt : $targetOrder->cancelled_at,
                'is_locked' => $toStatus === 'CANCELLED' ? true : (bool) $targetOrder->is_locked,
                'locked_at' => $toStatus === 'CANCELLED' && $statusChanged ? $statusChangedAt : $targetOrder->locked_at,
            ]);
            $targetOrder->save();

            $this->recordDebtCollectionOnOrderUpdate(
                user: $user,
                order: $targetOrder,
                previousPaidAmount: $previousPaidAmount,
                previousDebtAmount: $previousDebtAmount,
            );

            $this->customerDebtService->syncForCustomer(
                tenantId: (int) $user->tenant_id,
                customerId: $previousCustomerId,
            );
            $this->customerDebtService->syncForCustomer(
                tenantId: (int) $user->tenant_id,
                customerId: is_numeric($targetOrder->customer_id) ? (int) $targetOrder->customer_id : null,
            );

            if (array_key_exists('items', $data)) {
                $this->syncItems($targetOrder, $data['items']);
            }

            if (array_key_exists('surcharges', $data)) {
                $this->syncSurcharges($targetOrder, $data['surcharges']);
            }

            if (! $this->shouldDeductStock($fromStatus) && $this->shouldDeductStock($toStatus)) {
                $this->orderInventoryService->deductForOrder(
                    user: $user,
                    order: $targetOrder,
                    allowNegativeStock: $this->stockPolicyConfigService->allowNegativeStock($user),
                );
            }

            if ($toStatus === 'CANCELLED' && $statusChanged) {
                $this->cancelLinkedArtifactsForCancelledOrder($targetOrder);
            }

            if ($toStatus !== 'CANCELLED' && $replaceInvoice && $targetOrder->invoice_id) {
                $oldInvoice = $targetOrder->invoice()->first();

                if ($oldInvoice && (int) $oldInvoice->tenant_id === (int) $user->tenant_id) {
                    if (in_array($oldInvoice->status, ['CANCELLED', 'REPLACED'], true)) {
                        throw ValidationException::withMessages([
                            'replace_invoice' => 'Hóa đơn đã hủy hoặc đã bị thay thế, không thể phát hành lại từ đơn hàng này.',
                        ]);
                    }

                    $targetOrder->loadMissing('items.productVariant');

                    $newInvoice = $this->invoiceService->create($user, [
                        'parent_invoice_id' => $oldInvoice->id,
                        'order_id' => $targetOrder->id,
                        'customer_id' => $targetOrder->customer_id,
                        'subtotal' => (float) $targetOrder->total_amount,
                        'tax_rate' => 0,
                        'total_amount' => (float) $targetOrder->final_amount,
                        'sale_at' => $targetOrder->order_date ?? now(),
                        'items' => $targetOrder->items->map(fn ($item) => [
                            'product_variant_id' => (int) $item->product_variant_id,
                            'product_name' => $item->productVariant?->name ?? ('Variant #'.$item->product_variant_id),
                            'unit_name' => $item->unit_name,
                            'quantity' => (float) $item->quantity,
                            'price' => (float) $item->price,
                            'discount' => (float) ($item->discount ?? 0),
                            'tax_rate' => (float) ($item->tax_rate ?? 0),
                        ])->values()->all(),
                    ]);

                    $oldInvoice->status = 'REPLACED';
                    $oldInvoice->change_reason = $revisionReason !== ''
                        ? trim($revisionReason).' (Bị thay thế bởi hóa đơn '.$newInvoice->code.')'
                        : 'Bị thay thế bởi hóa đơn '.$newInvoice->code.' do cập nhật đơn hàng '.$targetOrder->code.'.';
                    $oldInvoice->save();

                    $targetOrder->invoice_id = $newInvoice->id;
                    $targetOrder->save();
                }
            }

            if (
                $toStatus === 'COMPLETED'
                && $this->invoiceUsageConfigService->useInvoiceOnSale($user)
                && $invoiceExportMode === 'NOW'
                && ! $targetOrder->invoice_id
            ) {
                $this->issueInvoiceForOrder($user, $targetOrder);
            }

            return $targetOrder->refresh()->load(['items.productVariant.product', 'surcharges', 'customer', 'warehouse', 'user']);
        });
    }

    private function shouldCreateOrderRevision(array $data): bool
    {
        return (bool) ($data['create_revision'] ?? false);
    }

    private function hasBusinessFieldChanges(array $data): bool
    {
        $mutableBusinessFields = [
            'customer_id',
            'warehouse_id',
            'seller_id',
            'order_date',
            'type',
            'discount',
            'promotion_id',
            'note',
            'items',
            'surcharges',
        ];

        foreach ($mutableBusinessFields as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }

        return false;
    }

    private function createOrderRevision(User $user, Order $sourceOrder, string $revisionReason = ''): Order
    {
        $sourceOrder->loadMissing(['items', 'surcharges']);
        $sourceInvoiceId = is_numeric($sourceOrder->invoice_id) && (int) $sourceOrder->invoice_id > 0
            ? (int) $sourceOrder->invoice_id
            : null;

        // Detach invoice from the source order first to satisfy orders.invoice_id unique constraint.
        if ($sourceInvoiceId !== null) {
            $sourceOrder->invoice_id = null;
            $sourceOrder->save();
        }

        $safeRevisionReason = $revisionReason !== ''
            ? $revisionReason
            : 'REVISION '.(string) now()->format('YmdHis');

        $revisionOrder = Order::query()->create([
            'tenant_id' => $sourceOrder->tenant_id,
            'code' => $this->codeSequenceService->nextCode($user, 'ORDER'),
            'parent_order_id' => $sourceOrder->id,
            'revision_number' => ((int) $sourceOrder->revision_number) + 1,
            'revision_reason' => mb_substr($safeRevisionReason, 0, 50),
            'invoice_id' => $sourceInvoiceId,
            'customer_id' => $sourceOrder->customer_id,
            'user_id' => $sourceOrder->user_id,
            'warehouse_id' => $sourceOrder->warehouse_id,
            'type' => $sourceOrder->type,
            'status' => $sourceOrder->status,
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'locked_reason' => null,
            'total_amount' => (float) $sourceOrder->total_amount,
            'discount' => (float) $sourceOrder->discount,
            'surcharge' => (float) $sourceOrder->surcharge,
            'final_amount' => (float) $sourceOrder->final_amount,
            'paid_amount' => (float) $sourceOrder->paid_amount,
            'debt_amount' => (float) $sourceOrder->debt_amount,
            'promotion_id' => $sourceOrder->promotion_id,
            'promo_discount' => (float) ($sourceOrder->promo_discount ?? 0),
            'note' => $sourceOrder->note,
            'order_date' => $sourceOrder->order_date,
            'completed_at' => $sourceOrder->completed_at,
            'processing_at' => $sourceOrder->processing_at,
            'confirmed_at' => $sourceOrder->confirmed_at,
            'cancelled_at' => $sourceOrder->cancelled_at,
        ]);

        foreach ($sourceOrder->items as $item) {
            $revisionOrder->items()->create([
                'product_variant_id' => $item->product_variant_id,
                'batch_id' => $item->batch_id,
                'unit_name' => $item->unit_name,
                'conversion_rate' => $item->conversion_rate,
                'quantity' => $item->quantity,
                'returned_quantity' => $item->returned_quantity,
                'price' => $item->price,
                'cost_price' => $item->cost_price,
                'discount' => $item->discount,
                'promo_discount' => $item->promo_discount,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'total' => $item->total,
                'promotion_id' => $item->promotion_id,
                'note' => $item->note,
            ]);
        }

        foreach ($sourceOrder->surcharges as $surcharge) {
            $revisionOrder->surcharges()->create([
                'name' => $surcharge->name,
                'type' => $surcharge->type,
                'value' => $surcharge->value,
                'amount' => $surcharge->amount,
                'is_refundable' => $surcharge->is_refundable,
            ]);
        }

        if ($sourceInvoiceId !== null) {
            Invoice::query()
                ->whereKey($sourceInvoiceId)
                ->update(['order_id' => $revisionOrder->id]);
        }

        $sourceOrder->is_locked = true;
        $sourceOrder->locked_at = now();
        $sourceOrder->locked_by = $user->id;
        $sourceOrder->locked_reason = mb_substr('REVISED_TO_'.$revisionOrder->code, 0, 50);
        $sourceOrder->save();

        return $revisionOrder;
    }

    private function recordDebtCollectionOnOrderUpdate(
        User $user,
        Order $order,
        float $previousPaidAmount,
        float $previousDebtAmount,
    ): void {
        $collectedAmount = round((float) $order->paid_amount - $previousPaidAmount, 2);
        if ($collectedAmount <= 0) {
            return;
        }

        $payment = Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'code' => $this->codeSequenceService->nextCode($user, 'PAYMENT'),
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'amount' => $collectedAmount,
            'method' => 'CASH',
            'status' => (float) $order->debt_amount > 0 ? 'PARTIAL' : 'COMPLETED',
            'paid_at' => now(),
            'note' => 'Thu bổ sung công nợ đơn hàng '.$order->code,
            'created_by' => $user->id,
        ]);

        if ($previousDebtAmount <= 0) {
            return;
        }

        if (! is_numeric($order->customer_id) || (int) $order->customer_id <= 0) {
            return;
        }

        $lastBalance = (float) AccountReceivable::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('customer_id', $order->customer_id)
            ->latest('id')
            ->value('balance_after');

        AccountReceivable::query()->create([
            'tenant_id' => $user->tenant_id,
            'customer_id' => $order->customer_id,
            'amount' => -$collectedAmount,
            'balance_after' => max(0, $lastBalance - $collectedAmount),
            'type' => 'PAYMENT',
            'status' => (float) $order->debt_amount > 0 ? 'PARTIAL' : 'PAID',
            'order_id' => $order->id,
            'invoice_id' => $order->invoice_id,
            'payment_id' => $payment->id,
            'credit_note_id' => null,
            'due_date' => null,
            'paid_at' => now(),
            'note' => 'Giảm trừ công nợ từ lần thu bổ sung cho đơn '.$order->code,
            'created_by' => $user->id,
        ]);
    }

    private function issueInvoiceForOrder(User $user, Order $order): void
    {
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
    }

    private function cancelLinkedArtifactsForCancelledOrder(Order $order): void
    {
        $order->loadMissing('invoice');

        $invoice = $order->invoice;
        if ($invoice && ! in_array(strtoupper((string) $invoice->status), ['CANCELLED', 'REPLACED'], true)) {
            $message = 'Hủy do đơn hàng '.$order->code.' chuyển trạng thái CANCELLED.';
            $existingReason = trim((string) ($invoice->change_reason ?? ''));

            $invoice->status = 'CANCELLED';
            $invoice->change_reason = $existingReason === '' ? $message : $existingReason.' | '.$message;
            $invoice->save();
        }

        $order->returnOrders()
            ->where('status', '!=', 'CANCELLED')
            ->update(['status' => 'CANCELLED']);
    }

    public function delete(User $user, Order $order): void
    {
        $this->guardMutableTenantOrder($user, $order);
        $order->delete();
    }

    private function calculateTotals(array $items, array $surcharges, float $orderDiscount = 0): array
    {
        $subtotal = collect($items)->sum(function (array $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);

            return max(0, ($qty * $price) - $discount);
        });

        $surcharge = collect($surcharges)->sum(fn (array $row) => (float) ($row['amount'] ?? 0));
        $discount = max(0, $orderDiscount);
        $final = max(0, $subtotal + $surcharge - $discount);

        return [
            'subtotal' => round($subtotal, 2),
            'surcharge' => round($surcharge, 2),
            'discount' => round($discount, 2),
            'final' => round($final, 2),
        ];
    }

    private function syncItems(Order $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $baseTotal = max(0, ($quantity * $price) - $discount);
            $taxAmount = round($baseTotal * ($taxRate / 100), 2);

            $order->items()->create([
                ...Arr::except($item, ['id']),
                'tax_amount' => $taxAmount,
                'total' => round($baseTotal + $taxAmount, 2),
            ]);
        }
    }

    private function syncSurcharges(Order $order, array $surcharges): void
    {
        $order->surcharges()->delete();
        foreach ($surcharges as $surcharge) {
            $order->surcharges()->create(Arr::except($surcharge, ['id']));
        }
    }

    private function guardMutableTenantOrder(User $user, Order $order): void
    {
        if ($order->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'order' => 'Order does not belong to this tenant.',
            ]);
        }

        if ((bool) $order->is_locked) {
            throw ValidationException::withMessages([
                'order' => 'Locked orders cannot be modified.',
            ]);
        }
    }

    private function shouldDeductStock(string $status): bool
    {
        return in_array(strtoupper($status), ['CONFIRMED', 'PROCESSING', 'COMPLETED'], true);
    }

    private function guardOrderStatusTransition(User $user, string $fromStatus, string $toStatus): void
    {
        if (! $this->shouldUseOrderStateMachine()) {
            $this->assertLegacyOrderStatusTransition($fromStatus, $toStatus);

            return;
        }

        $context = TransitionContext::actor(
            actorId: (int) $user->id,
            tenantId: (int) $user->tenant_id,
            correlationId: (string) Str::uuid(),
            metadata: [
                'aggregate' => 'order',
                'service' => self::class,
            ],
        );

        $result = $this->orderStateMachine->transition($fromStatus, $toStatus, $context);

        if ($this->isDualOrderRolloutMode()) {
            $legacyAccepted = $this->legacyCanTransition($fromStatus, $toStatus);

            if ($legacyAccepted !== $result->accepted && $this->isStateMachineAuditEnabled()) {
                Log::channel($this->stateMachineAuditChannel())->warning('Order transition divergence detected', [
                    'from' => strtoupper($fromStatus),
                    'to' => strtoupper($toStatus),
                    'tenant_id' => (int) $user->tenant_id,
                    'actor_id' => (int) $user->id,
                    'legacy_accepted' => $legacyAccepted,
                    'state_machine_accepted' => $result->accepted,
                    'state_machine_error_code' => $result->errorCode,
                    'state_machine_reason' => $result->reason,
                ]);
            }
        }

        if (! $result->accepted) {
            throw ValidationException::withMessages([
                'status' => $result->reason ?? 'Luồng trạng thái không hợp lệ.',
            ]);
        }
    }

    private function assertLegacyOrderStatusTransition(string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        if ($fromStatus === 'CANCELLED') {
            throw ValidationException::withMessages([
                'status' => 'Đơn đã hủy không thể thay đổi trạng thái lại.',
            ]);
        }

        $allowedTransitions = [
            'PREORDER' => ['DRAFT', 'PROCESSING', 'CONFIRMED', 'COMPLETED', 'CANCELLED'],
            'DRAFT' => ['PREORDER', 'PROCESSING', 'CONFIRMED', 'COMPLETED', 'CANCELLED'],
            'PROCESSING' => ['CONFIRMED', 'COMPLETED', 'CANCELLED'],
            'CONFIRMED' => ['COMPLETED', 'CANCELLED'],
            'COMPLETED' => ['CANCELLED'],
        ];

        $nextStatuses = $allowedTransitions[$fromStatus] ?? [];
        if (! in_array($toStatus, $nextStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => 'Luồng trạng thái không hợp lệ. Chỉ cho phép: PREORDER/DRAFT -> PROCESSING -> CONFIRMED -> COMPLETED hoặc chuyển CANCELLED.',
            ]);
        }
    }

    private function legacyCanTransition(string $fromStatus, string $toStatus): bool
    {
        try {
            $this->assertLegacyOrderStatusTransition($fromStatus, $toStatus);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function shouldUseOrderStateMachine(): bool
    {
        if ((bool) config('state_machine.flags.order', false)) {
            return true;
        }

        return in_array((string) config('state_machine.rollout_mode', 'legacy'), ['state_machine', 'dual'], true);
    }

    private function isDualOrderRolloutMode(): bool
    {
        return (string) config('state_machine.rollout_mode', 'legacy') === 'dual';
    }

    private function isStateMachineAuditEnabled(): bool
    {
        return (bool) config('state_machine.audit.enabled', true);
    }

    private function stateMachineAuditChannel(): string
    {
        return (string) config('state_machine.audit.channel', (string) config('logging.default', 'stack'));
    }

    private function canEditCompletedOrderContent(
        User $user,
        Order $order,
        string $fromStatus,
        string $toStatus,
        bool $hasBusinessFieldChanges,
    ): bool {
        if ($fromStatus !== 'COMPLETED' || ! $hasBusinessFieldChanges) {
            return false;
        }

        if ($toStatus !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'status' => 'Khi sửa nội dung đơn hàng hoàn thành, trạng thái phải giữ nguyên COMPLETED.',
            ]);
        }

        $order->loadMissing('invoice');
        if (! $order->invoice_id || ! $order->invoice) {
            return true;
        }

        if (strtoupper((string) $order->invoice->status) !== 'ISSUED') {
            throw ValidationException::withMessages([
                'order' => 'Hóa đơn liên kết không còn ở trạng thái phát hành hợp lệ để thay thế.',
            ]);
        }

        if (strtoupper((string) ($order->invoice->e_invoice_status ?? 'NOT_EXPORTED')) === 'EXPORTED') {
            throw ValidationException::withMessages([
                'order' => 'Hóa đơn đã export lên cục thuế nên không thể chỉnh sửa nội dung đơn hoàn thành.',
            ]);
        }

        return true;
    }

    private function guardCompletedOrderTaxExportLock(Order $order, string $fromStatus, string $toStatus, array $data): void
    {
        if ($fromStatus !== 'COMPLETED') {
            return;
        }

        $order->loadMissing('invoice');
        $invoice = $order->invoice;
        if (! $invoice) {
            return;
        }

        $eInvoiceStatus = strtoupper((string) ($invoice->e_invoice_status ?? 'NOT_EXPORTED'));
        if ($eInvoiceStatus !== 'EXPORTED') {
            return;
        }

        $hasAnyChanges = $fromStatus !== $toStatus || count($data) > 0;
        if (! $hasAnyChanges) {
            return;
        }

        throw ValidationException::withMessages([
            'order' => 'Đơn hàng hoàn thành đã export hóa đơn lên cục thuế nên không thể chỉnh sửa hoặc hủy.',
        ]);
    }

    private function guardImmutableFieldsWhenOrderIsProcessing(string $currentStatus, array $data, bool $allowCompletedContentEdit = false): void
    {
        $protectedStatuses = ['PROCESSING', 'CONFIRMED', 'COMPLETED'];
        if (! in_array($currentStatus, $protectedStatuses, true)) {
            return;
        }

        if ($currentStatus === 'COMPLETED' && $allowCompletedContentEdit) {
            return;
        }

        $forbiddenFields = [
            'items',
            'surcharges',
            'warehouse_id',
            'customer_id',
            'type',
            'discount',
            'note',
            'order_date',
        ];

        foreach ($forbiddenFields as $field) {
            if (array_key_exists($field, $data)) {
                throw ValidationException::withMessages([
                    $field => 'Đơn ở trạng thái xử lý/xác nhận/hoàn thành chỉ được cập nhật trạng thái, không được sửa thông tin khác.',
                ]);
            }
        }
    }

    private function guardPaymentPolicyForOrder(mixed $customerId, float $paidAmount, float $finalAmount): void
    {
        if ($finalAmount <= 0) {
            return;
        }

        $hasDebt = $paidAmount < $finalAmount;
        if (! $hasDebt) {
            return;
        }

        if (! is_numeric($customerId) || (int) $customerId <= 0) {
            throw ValidationException::withMessages([
                'customer_id' => 'Đơn có công nợ (ghi nợ toàn bộ hoặc một phần) bắt buộc phải chọn khách hàng.',
            ]);
        }
    }

    private function recordFinancialEntries(User $user, Order $order, float $paidAmount, float $debtAmount): void
    {
        if ($paidAmount > 0) {
            Payment::query()->create([
                'tenant_id' => $user->tenant_id,
                'code' => $this->codeSequenceService->nextCode($user, 'PAYMENT'),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'amount' => $paidAmount,
                'method' => 'CASH',
                'status' => $debtAmount > 0 ? 'PARTIAL' : 'COMPLETED',
                'paid_at' => now(),
                'note' => 'Thu tiền từ đơn hàng '.$order->code,
                'created_by' => $user->id,
            ]);
        }

        if ($debtAmount > 0 && $order->customer_id !== null) {
            $lastBalance = (float) AccountReceivable::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('customer_id', $order->customer_id)
                ->latest('id')
                ->value('balance_after');

            AccountReceivable::query()->create([
                'tenant_id' => $user->tenant_id,
                'customer_id' => $order->customer_id,
                'amount' => $debtAmount,
                'balance_after' => $lastBalance + $debtAmount,
                'type' => 'INVOICE',
                'status' => 'OPEN',
                'order_id' => $order->id,
                'invoice_id' => $order->invoice_id,
                'payment_id' => null,
                'credit_note_id' => null,
                'due_date' => null,
                'paid_at' => null,
                'note' => 'Ghi nhận công nợ từ đơn hàng '.$order->code,
                'created_by' => $user->id,
            ]);
        }
    }
}
