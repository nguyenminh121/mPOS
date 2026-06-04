<?php

namespace App\Services\Tenant;

use App\Models\BusinessExpense;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessExpenseService
{
    public function __construct(
        private readonly CodeSequenceService $codeSequenceService,
    ) {
    }

    public function list(User $user): Collection
    {
        return BusinessExpense::query()
            ->where('tenant_id', $user->tenant_id)
            ->with([
                'order:id,code',
                'supplier:id,code,name',
                'creator:id,name',
                'approvedByUser:id,name',
            ])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();
    }

    public function create(User $user, array $data): BusinessExpense
    {
        return DB::transaction(function () use ($user, $data): BusinessExpense {
            $this->assertOrderBelongsToTenant($user, Arr::get($data, 'order_id'));
            $this->assertSupplierBelongsToTenant($user, Arr::get($data, 'supplier_id'));

            $expense = BusinessExpense::query()->create([
                ...Arr::only($data, [
                    'name',
                    'description',
                    'category',
                    'amount',
                    'order_id',
                    'supplier_id',
                    'payment_method',
                    'payment_status',
                    'expense_date',
                    'note',
                ]),
                'code' => $this->generateCode($user),
                'tenant_id' => $user->tenant_id,
                'created_by' => $user->id,
                'payment_status' => strtoupper((string) Arr::get($data, 'payment_status', 'PENDING')),
                'paid_at' => strtoupper((string) Arr::get($data, 'payment_status', 'PENDING')) === 'PAID' ? now() : null,
                'expense_date' => Arr::get($data, 'expense_date') ?? now(),
            ]);

            $expense->audits()->create([
                'action' => 'CREATED',
                'before_json' => null,
                'after_json' => $expense->only([
                    'name',
                    'description',
                    'category',
                    'amount',
                    'order_id',
                    'supplier_id',
                    'payment_method',
                    'payment_status',
                    'expense_date',
                    'note',
                ]),
                'change_note' => 'Tạo chi phí doanh nghiệp',
                'created_by' => $user->id,
            ]);

            return $expense->refresh();
        });
    }

    public function update(User $user, BusinessExpense $expense, array $data): BusinessExpense
    {
        $this->guardTenant($user, $expense);

        return DB::transaction(function () use ($user, $expense, $data): BusinessExpense {
            if (array_key_exists('order_id', $data)) {
                $this->assertOrderBelongsToTenant($user, Arr::get($data, 'order_id'));
            }

            if (array_key_exists('supplier_id', $data)) {
                $this->assertSupplierBelongsToTenant($user, Arr::get($data, 'supplier_id'));
            }

            $before = $expense->only([
                'name',
                'description',
                'category',
                'amount',
                'order_id',
                'supplier_id',
                'payment_method',
                'payment_status',
                'paid_at',
                'expense_date',
                'note',
            ]);

            $expense->fill(Arr::only($data, [
                'name',
                'description',
                'category',
                'amount',
                'order_id',
                'supplier_id',
                'payment_method',
                'payment_status',
                'expense_date',
                'note',
            ]));

            if (array_key_exists('payment_status', $data)) {
                $status = strtoupper((string) Arr::get($data, 'payment_status', 'PENDING'));
                $expense->payment_status = $status;
                $expense->paid_at = $status === 'PAID' ? ($expense->paid_at ?? now()) : null;
            }

            $expense->save();

            $after = $expense->only([
                'name',
                'description',
                'category',
                'amount',
                'order_id',
                'supplier_id',
                'payment_method',
                'payment_status',
                'paid_at',
                'expense_date',
                'note',
            ]);

            $expense->audits()->create([
                'action' => 'UPDATED',
                'before_json' => $before,
                'after_json' => $after,
                'change_note' => 'Cập nhật chi phí doanh nghiệp',
                'created_by' => $user->id,
            ]);

            return $expense->refresh();
        });
    }

    public function delete(User $user, BusinessExpense $expense): void
    {
        $this->guardTenant($user, $expense);

        DB::transaction(function () use ($user, $expense): void {
            $before = $expense->only([
                'name',
                'description',
                'category',
                'amount',
                'order_id',
                'supplier_id',
                'payment_method',
                'payment_status',
                'paid_at',
                'expense_date',
                'note',
            ]);

            $expense->audits()->create([
                'action' => 'DELETED',
                'before_json' => $before,
                'after_json' => null,
                'change_note' => 'Xóa chi phí doanh nghiệp',
                'created_by' => $user->id,
            ]);

            $expense->delete();
        });
    }

    private function generateCode(User $user): string
    {
        return sprintf('T%s-%s', $user->tenant_id, $this->codeSequenceService->nextCode($user, 'BUSINESS_EXPENSE'));
    }

    private function assertOrderBelongsToTenant(User $user, mixed $orderId): void
    {
        if ($orderId === null) {
            return;
        }

        $exists = Order::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey((int) $orderId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'order_id' => 'Đơn hàng không tồn tại trong tenant.',
            ]);
        }
    }

    private function assertSupplierBelongsToTenant(User $user, mixed $supplierId): void
    {
        if ($supplierId === null) {
            return;
        }

        $exists = Supplier::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey((int) $supplierId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Nhà cung cấp không tồn tại trong tenant.',
            ]);
        }
    }

    private function guardTenant(User $user, BusinessExpense $expense): void
    {
        if ((int) $expense->tenant_id !== (int) $user->tenant_id) {
            throw ValidationException::withMessages([
                'expense' => 'Business expense does not belong to this tenant.',
            ]);
        }
    }
}
