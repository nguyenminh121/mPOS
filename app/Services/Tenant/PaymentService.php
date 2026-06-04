<?php

namespace App\Services\Tenant;

use App\Models\AccountReceivable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAudit;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly CodeSequenceService $codeSequenceService,
        private readonly CustomerDebtService $customerDebtService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function recordOrderDebtPayment(User $user, array $data): Payment
    {
        return DB::transaction(function () use ($user, $data): Payment {
            $order = Order::query()
                ->where('tenant_id', $user->tenant_id)
                ->findOrFail((int) Arr::get($data, 'order_id'));

            $this->guardOrderPayable($order);

            $currentDebt = max(0, (float) ($order->debt_amount ?? 0));
            $amount = max(0, round((float) Arr::get($data, 'amount', 0), 2));

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền thu phải lớn hơn 0.',
                ]);
            }

            if ($amount > $currentDebt) {
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền thu không được vượt quá công nợ hiện tại.',
                ]);
            }

            $collectorId = $this->resolveCollectorId($user, Arr::get($data, 'collector_id'));
            $method = strtoupper(trim((string) Arr::get($data, 'method', 'CASH')));

            $payment = $this->applyOrderDebtPayment(
                user: $user,
                order: $order,
                amount: $amount,
                collectorId: $collectorId,
                method: $method,
                data: [
                    'reference_code' => Arr::get($data, 'reference_code'),
                    'bank_name' => Arr::get($data, 'bank_name'),
                    'account_number' => Arr::get($data, 'account_number'),
                    'note' => Arr::get($data, 'note'),
                    'paid_at' => Arr::get($data, 'paid_at'),
                ],
                paymentAuditNote: 'Tạo phiếu thu công nợ từ màn hình khách hàng',
                receivableNotePrefix: 'Thu công nợ từ khách hàng cho đơn ',
                auditAction: 'PAYMENT_CREATED',
            );

            $this->customerDebtService->syncForCustomer(
                tenantId: (int) $user->tenant_id,
                customerId: is_numeric($order->customer_id) ? (int) $order->customer_id : null,
            );

            return $payment->refresh()->load(['order:id,code,status', 'customer:id,name', 'creator:id,name']);
        });
    }

    public function recordCustomerDebtPaymentFIFO(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $customerId = (int) Arr::get($data, 'customer_id');
            $requestedAmount = max(0, round((float) Arr::get($data, 'amount', 0), 2));

            if ($requestedAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền thu phải lớn hơn 0.',
                ]);
            }

            $collectorId = $this->resolveCollectorId($user, Arr::get($data, 'collector_id'));
            $method = strtoupper(trim((string) Arr::get($data, 'method', 'CASH')));
            $orderIds = collect(Arr::wrap(Arr::get($data, 'order_ids')))
                ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all();

            $ordersQuery = Order::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('customer_id', $customerId)
                ->where('status', '!=', 'CANCELLED')
                ->where('debt_amount', '>', 0);

            if ($orderIds !== []) {
                $ordersQuery->whereIn('id', $orderIds);
            }

            $orders = $ordersQuery
                ->orderByRaw('COALESCE(order_date, created_at) asc')
                ->orderBy('id')
                ->get();

            if ($orders->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_ids' => 'Không có đơn hàng công nợ phù hợp để thu tiền.',
                ]);
            }

            $remaining = $requestedAmount;
            $payments = [];
            $allocations = [];

            foreach ($orders as $order) {
                if ($remaining <= 0) {
                    break;
                }

                $orderDebtBefore = max(0, (float) ($order->debt_amount ?? 0));
                if ($orderDebtBefore <= 0) {
                    continue;
                }

                $collectAmount = min($orderDebtBefore, $remaining);
                if ($collectAmount <= 0) {
                    continue;
                }

                $payment = $this->applyOrderDebtPayment(
                    user: $user,
                    order: $order,
                    amount: $collectAmount,
                    collectorId: $collectorId,
                    method: $method,
                    data: [
                        'reference_code' => Arr::get($data, 'reference_code'),
                        'bank_name' => Arr::get($data, 'bank_name'),
                        'account_number' => Arr::get($data, 'account_number'),
                        'note' => Arr::get($data, 'note'),
                        'paid_at' => Arr::get($data, 'paid_at'),
                    ],
                    paymentAuditNote: 'Tạo phiếu thu công nợ FIFO từ màn hình khách hàng',
                    receivableNotePrefix: 'Thu công nợ FIFO cho đơn ',
                    auditAction: 'PAYMENT_CREATED_FIFO',
                );

                $remaining = max(0, round($remaining - $collectAmount, 2));
                $orderDebtAfter = max(0, (float) ($order->debt_amount ?? 0));

                $payments[] = [
                    'id' => (int) $payment->id,
                    'code' => $payment->code,
                    'order_id' => (int) $order->id,
                    'order_code' => $order->code,
                    'amount' => (float) $payment->amount,
                ];

                $allocations[] = [
                    'order_id' => (int) $order->id,
                    'order_code' => $order->code,
                    'debt_before' => round($orderDebtBefore, 2),
                    'collected_amount' => round($collectAmount, 2),
                    'debt_after' => round($orderDebtAfter, 2),
                ];
            }

            if ($allocations === []) {
                throw ValidationException::withMessages([
                    'amount' => 'Không thể phân bổ số tiền thu vào đơn hàng công nợ.',
                ]);
            }

            $this->customerDebtService->syncForCustomer(
                tenantId: (int) $user->tenant_id,
                customerId: $customerId,
            );

            return [
                'requested_amount' => round($requestedAmount, 2),
                'collected_amount' => round($requestedAmount - $remaining, 2),
                'remaining_unallocated' => round($remaining, 2),
                'payments' => $payments,
                'allocations' => $allocations,
            ];
        });
    }

    private function applyOrderDebtPayment(
        User $user,
        Order $order,
        float $amount,
        int $collectorId,
        string $method,
        array $data,
        string $paymentAuditNote,
        string $receivableNotePrefix,
        string $auditAction,
    ): Payment {
        $this->guardOrderPayable($order);

        $currentDebt = max(0, (float) ($order->debt_amount ?? 0));
        $currentPaid = max(0, (float) ($order->paid_amount ?? 0));
        $normalizedAmount = max(0, round((float) $amount, 2));

        if ($normalizedAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền thu phải lớn hơn 0.',
            ]);
        }

        if ($normalizedAmount > $currentDebt) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền thu không được vượt quá công nợ hiện tại.',
            ]);
        }

        $paidAt = Arr::get($data, 'paid_at') ?? now();

        $payment = Payment::query()->create([
            'tenant_id' => (int) $user->tenant_id,
            'code' => $this->codeSequenceService->nextCode($user, 'PAYMENT'),
            'order_id' => (int) $order->id,
            'customer_id' => $order->customer_id,
            'amount' => $normalizedAmount,
            'method' => $method,
            'status' => $normalizedAmount < $currentDebt ? 'PARTIAL' : 'COMPLETED',
            'reference_code' => Arr::get($data, 'reference_code'),
            'bank_name' => Arr::get($data, 'bank_name'),
            'account_number' => Arr::get($data, 'account_number'),
            'note' => Arr::get($data, 'note'),
            'paid_at' => $paidAt,
            'created_by' => $collectorId,
        ]);

        $beforeOrder = [
            'paid_amount' => $currentPaid,
            'debt_amount' => $currentDebt,
        ];

        $order->paid_amount = round($currentPaid + $normalizedAmount, 2);
        $order->debt_amount = max(0, round($currentDebt - $normalizedAmount, 2));
        $order->save();

        $lastBalance = (float) AccountReceivable::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('customer_id', $order->customer_id)
            ->latest('id')
            ->value('balance_after');

        AccountReceivable::query()->create([
            'tenant_id' => (int) $user->tenant_id,
            'customer_id' => $order->customer_id,
            'amount' => -$normalizedAmount,
            'balance_after' => max(0, round($lastBalance - $normalizedAmount, 2)),
            'type' => 'PAYMENT',
            'status' => (float) $order->debt_amount > 0 ? 'PARTIAL' : 'PAID',
            'order_id' => (int) $order->id,
            'invoice_id' => $order->invoice_id,
            'payment_id' => (int) $payment->id,
            'credit_note_id' => null,
            'due_date' => null,
            'paid_at' => $paidAt,
            'note' => $receivableNotePrefix.$order->code,
            'created_by' => (int) $collectorId,
        ]);

        PaymentAudit::query()->create([
            'payment_id' => (int) $payment->id,
            'action' => 'CREATED',
            'before_json' => null,
            'after_json' => $payment->toArray(),
            'change_note' => $paymentAuditNote,
            'created_by' => (int) $user->id,
        ]);

        $this->auditLogService->record(
            user: $user,
            action: $auditAction,
            tableName: 'payments',
            recordId: (int) $payment->id,
            oldValue: null,
            newValue: [
                'payment' => $payment->toArray(),
                'order_before' => $beforeOrder,
                'order_after' => [
                    'paid_amount' => (float) $order->paid_amount,
                    'debt_amount' => (float) $order->debt_amount,
                ],
            ],
        );

        return $payment;
    }

    private function resolveCollectorId(User $user, mixed $collectorId): int
    {
        if (! is_numeric($collectorId) || (int) $collectorId <= 0) {
            return (int) $user->id;
        }

        $targetId = (int) $collectorId;

        if ($targetId === (int) $user->id) {
            return $targetId;
        }

        if (! $user->hasAnyRole(['admin', 'manager'])) {
            return (int) $user->id;
        }

        $collectorExists = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($targetId)
            ->exists();

        return $collectorExists ? $targetId : (int) $user->id;
    }

    private function guardOrderPayable(Order $order): void
    {
        if ((int) ($order->customer_id ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'order_id' => 'Đơn hàng chưa gắn khách hàng, không thể thu công nợ.',
            ]);
        }

        if (strtoupper((string) ($order->status ?? '')) === 'CANCELLED') {
            throw ValidationException::withMessages([
                'order_id' => 'Đơn hàng đã hủy, không thể ghi nhận thanh toán.',
            ]);
        }

        if ((float) ($order->debt_amount ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'order_id' => 'Đơn hàng không còn công nợ để thu.',
            ]);
        }
    }
}
