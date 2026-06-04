<?php

namespace App\Services\Tenant;

use App\Models\ImportItem;
use App\Models\ImportReceipt;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportReceiptService
{
    private const ALLOWED_STATUSES = ['DRAFT', 'PENDING', 'APPROVED', 'COMPLETED', 'CANCELLED'];
    private const RECEIPT_ACTIVE_STATUS = 'COMPLETED';
    private const STATUS_RANK = [
        'DRAFT' => 1,
        'PENDING' => 2,
        'APPROVED' => 3,
        'COMPLETED' => 4,
        'CANCELLED' => 5,
    ];

    public function list(User $user): Collection
    {
        return ImportReceipt::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['supplier:id,name', 'warehouse:id,name', 'creator:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->get();
    }

    public function create(User $user, array $data): ImportReceipt
    {
        return DB::transaction(function () use ($user, $data): ImportReceipt {
            $status = $this->normalizeStatus((string) Arr::get($data, 'status', self::RECEIPT_ACTIVE_STATUS));

            if ($status === 'CANCELLED') {
                throw ValidationException::withMessages([
                    'status' => 'Không thể tạo phiếu nhập mới ở trạng thái hủy.',
                ]);
            }

            $receipt = ImportReceipt::query()->create([
                'tenant_id' => $user->tenant_id,
                'code' => $this->generateCode($user),
                'purchase_order_id' => Arr::get($data, 'purchase_order_id'),
                'supplier_id' => Arr::get($data, 'supplier_id'),
                'warehouse_id' => Arr::get($data, 'warehouse_id'),
                'creator_id' => $user->id,
                'status' => $status,
                'import_date' => Arr::get($data, 'import_date') ?? now(),
                'note' => Arr::get($data, 'note'),
                'parent_id' => Arr::get($data, 'parent_id'),
            ]);

            $this->syncItems($receipt, Arr::get($data, 'items', []));
            $this->recalculateAmount($receipt);

            if ($status === self::RECEIPT_ACTIVE_STATUS) {
                $this->postStock($receipt, $user);
            }

            return $receipt->refresh()->load(['items.productVariant', 'supplier', 'warehouse', 'creator']);
        });
    }

    public function update(User $user, ImportReceipt $receipt, array $data): ImportReceipt
    {
        $this->guardTenant($user, $receipt);

        return DB::transaction(function () use ($user, $receipt, $data): ImportReceipt {
            $fromStatus = $this->normalizeStatus((string) $receipt->status);
            $toStatus = $this->normalizeStatus((string) Arr::get($data, 'status', $fromStatus));

            if ($fromStatus === 'CANCELLED') {
                throw ValidationException::withMessages([
                    'receipt' => 'Phiếu nhập đã hủy, không thể chỉnh sửa.',
                ]);
            }

            $this->assertForwardTransition($fromStatus, $toStatus);
            $this->assertSoDTransition($user, $receipt, $fromStatus, $toStatus);

            if ($fromStatus === 'COMPLETED' && $toStatus !== 'CANCELLED') {
                throw ValidationException::withMessages([
                    'status' => 'Phiếu nhập đã hoàn tất chỉ có thể chuyển sang trạng thái hủy. Nếu cần điều chỉnh, hãy tạo phiếu nhập mới.',
                ]);
            }

            if ($fromStatus === 'COMPLETED' && $toStatus === 'CANCELLED') {
                $receipt->loadMissing('items.productVariant', 'items.stockBatches');
                $this->reversePostedStock($receipt, $user);

                $receipt->status = 'CANCELLED';
                $receipt->note = Arr::get($data, 'note', $receipt->note);
                $receipt->save();

                return $receipt->refresh()->load(['items.productVariant', 'supplier', 'warehouse', 'creator', 'parent']);
            }

            $receipt->fill([
                'purchase_order_id' => Arr::get($data, 'purchase_order_id', $receipt->purchase_order_id),
                'supplier_id' => Arr::get($data, 'supplier_id', $receipt->supplier_id),
                'warehouse_id' => Arr::get($data, 'warehouse_id', $receipt->warehouse_id),
                'status' => $toStatus,
                'import_date' => Arr::get($data, 'import_date', $receipt->import_date ?? now()),
                'note' => Arr::get($data, 'note', $receipt->note),
            ]);
            $receipt->save();

            $incomingItems = Arr::get($data, 'items');
            if (is_array($incomingItems) && $incomingItems !== []) {
                $this->syncItems($receipt, $this->sanitizeItemsForRevision($incomingItems));
            }

            $this->recalculateAmount($receipt);

            if ($fromStatus !== self::RECEIPT_ACTIVE_STATUS && $toStatus === self::RECEIPT_ACTIVE_STATUS) {
                $this->postStock($receipt->refresh(), $user);
            }

            return $receipt->refresh()->load(['items.productVariant', 'supplier', 'warehouse', 'creator', 'parent']);
        });
    }

    public function delete(User $user, ImportReceipt $receipt): void
    {
        $this->guardTenant($user, $receipt);

        DB::transaction(function () use ($user, $receipt): void {
            $status = $this->normalizeStatus((string) $receipt->status);
            $isAdmin = $user->hasRole('admin');

            if (! $isAdmin && $receipt->children()->where('status', '!=', 'CANCELLED')->exists()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Phiếu nhập đã có bản sửa đổi kế thừa, không thể xóa trực tiếp.',
                ]);
            }

            if (! $isAdmin && in_array($status, ['APPROVED', 'COMPLETED'], true) && ! $user->hasRole('manager')) {
                throw ValidationException::withMessages([
                    'receipt' => 'Chỉ quản lý/admin mới được xóa phiếu đã duyệt hoặc hoàn tất.',
                ]);
            }

            if ($isAdmin) {
                if ($status === 'COMPLETED') {
                    $receipt->loadMissing('items.productVariant', 'items.stockBatches');
                    $this->reversePostedStock($receipt, $user);
                }

                $receipt->items()->delete();
                $receipt->delete();
                return;
            }

            if ($status === 'DRAFT') {
                $receipt->items()->delete();
                $receipt->delete();
                return;
            }

            if ($status === 'CANCELLED') {
                return;
            }

            $receipt->loadMissing('items.productVariant', 'items.stockBatches');

            if ($status === 'COMPLETED') {
                $this->reversePostedStock($receipt, $user);
            }

            $receipt->status = 'CANCELLED';
            $receipt->save();
        });
    }

    private function syncItems(ImportReceipt $receipt, array $items): void
    {
        $existingIds = $receipt->items()->pluck('id')->all();
        $incomingIds = collect($items)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $idsToDelete = array_diff($existingIds, $incomingIds);
        if (! empty($idsToDelete)) {
            $receipt->items()->whereIn('id', $idsToDelete)->delete();
        }

        foreach ($items as $item) {
            $payload = Arr::only($item, [
                'product_variant_id',
                'unit_name',
                'conversion_rate',
                'quantity',
                'quantity_in_base',
                'import_price',
                'tax_rate',
                'note',
            ]);

            $quantityInBase = (int) ($payload['quantity_in_base'] ?? 0);
            $importPrice = (float) ($payload['import_price'] ?? 0);
            $taxRate = (float) ($payload['tax_rate'] ?? 0);

            $lineAmount = $quantityInBase * $importPrice;
            $taxAmount = ($lineAmount * $taxRate) / 100;

            $payload['tax_amount'] = $taxAmount;
            $payload['total'] = $lineAmount + $taxAmount;

            if (! empty($item['id'])) {
                $updated = $receipt->items()->where('id', (int) $item['id'])->update($payload);
                if ($updated === 0) {
                    $receipt->items()->create($payload);
                }
            } else {
                $receipt->items()->create($payload);
            }
        }
    }

    private function recalculateAmount(ImportReceipt $receipt): void
    {
        $total = (float) $receipt->items()->sum('total');
        $paidAmount = (float) $receipt->paid_amount;

        $receipt->total_amount = $total;
        $receipt->debt_amount = max(0, $total - $paidAmount);
        $receipt->save();
    }

    private function postStock(ImportReceipt $receipt, User $user): void
    {
        $receipt->loadMissing('items.productVariant');

        foreach ($receipt->items as $item) {
            $variant = $item->productVariant;
            if (! $variant instanceof ProductVariant) {
                continue;
            }

            $quantity = (int) $item->quantity_in_base;

            $stock = Stock::query()->firstOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $receipt->warehouse_id,
                ],
                [
                    'quantity' => 0,
                    'warehouse_location_id' => null,
                ],
            );

            $stock->quantity += $quantity;
            $stock->save();

            $batch = StockBatch::query()->create([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $receipt->warehouse_id,
                'import_item_id' => $item->id,
                'batch_code' => $this->buildBatchCode($receipt, $item),
                'cost_price' => $item->import_price,
                'initial_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'selling_price' => $variant->price,
                'wholesale_price' => $variant->wholesale_price,
                'received_at' => $receipt->import_date ?? now(),
                'is_active' => true,
            ]);

            StockMovement::query()->create([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $receipt->warehouse_id,
                'batch_id' => $batch->id,
                'movement_type' => 'IMPORT',
                'quantity' => $quantity,
                'balance_after' => $stock->quantity,
                'cost_price_at_move' => $item->import_price,
                'import_item_id' => $item->id,
                'note' => "Nhập kho từ phiếu {$receipt->code}",
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }
    }

    private function generateCode(User $user): string
    {
        $prefix = 'PNK-' . now()->format('Ymd');

        $lastCode = ImportReceipt::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('code', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('code');

        if (! is_string($lastCode)) {
            return $prefix . '-0001';
        }

        $lastSequence = (int) substr($lastCode, -4);

        return $prefix . '-' . str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    private function buildBatchCode(ImportReceipt $receipt, ImportItem $item): string
    {
        return sprintf('B%s-%04d', $receipt->id, $item->id);
    }

    private function sanitizeItemsForRevision(array $items): array
    {
        return collect($items)
            ->map(function ($item): array {
                if (! is_array($item)) {
                    return [];
                }

                $sanitized = Arr::except($item, ['id']);

                return Arr::only($sanitized, [
                    'product_variant_id',
                    'unit_name',
                    'conversion_rate',
                    'quantity',
                    'quantity_in_base',
                    'import_price',
                    'tax_rate',
                    'note',
                ]);
            })
            ->filter(fn (array $item) => ! empty($item))
            ->values()
            ->all();
    }

    private function reversePostedStock(ImportReceipt $receipt, User $user): void
    {
        foreach ($receipt->items as $item) {
            $variant = $item->productVariant;
            if (! $variant instanceof ProductVariant) {
                continue;
            }

            $quantity = (int) $item->quantity_in_base;
            if ($quantity <= 0) {
                continue;
            }

            $stock = Stock::query()->firstOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $receipt->warehouse_id,
                ],
                [
                    'quantity' => 0,
                    'warehouse_location_id' => null,
                ],
            );

            $relatedBatches = $item->stockBatches
                ->where('warehouse_id', (int) $receipt->warehouse_id)
                ->where('product_variant_id', (int) $variant->id);

            $consumedQuantity = $relatedBatches->sum(fn ($batch) => max(0, ((int) $batch->initial_quantity) - ((int) $batch->remaining_quantity)));
            if ($consumedQuantity > 0) {
                throw ValidationException::withMessages([
                    'receipt' => 'Phiếu nhập đã có hàng xuất kho từ lô liên quan, không thể sửa trực tiếp.',
                ]);
            }

            if ((int) $stock->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'receipt' => 'Tồn kho hiện tại không đủ để hoàn tác phiếu nhập đã hoàn tất.',
                ]);
            }

            $stock->quantity -= $quantity;
            $stock->save();

            foreach ($relatedBatches as $batch) {
                $batch->remaining_quantity = 0;
                $batch->is_active = false;
                $batch->save();
            }

            StockMovement::query()->create([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $receipt->warehouse_id,
                'batch_id' => null,
                'movement_type' => 'ADJUST_OUT',
                'quantity' => $quantity,
                'balance_after' => (int) $stock->quantity,
                'cost_price_at_move' => $item->import_price,
                'import_item_id' => $item->id,
                'note' => "Hoàn tác phiếu {$receipt->code} để tạo bản sửa đổi",
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }
    }

    private function assertSoDTransition(User $user, ImportReceipt $receipt, string $fromStatus, string $toStatus): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($toStatus === 'CANCELLED' && ! $user->hasAnyRole(['manager'])) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ quản lý mới được hủy phiếu nhập.',
            ]);
        }

        if ($toStatus === 'APPROVED' && ! $user->hasAnyRole(['manager'])) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ quản lý mới được duyệt phiếu nhập.',
            ]);
        }

        if ($toStatus === 'COMPLETED') {
            if (! $user->hasAnyRole(['manager', 'accountant'])) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ quản lý/kế toán mới được hoàn tất phiếu nhập.',
                ]);
            }

            if ((int) $receipt->creator_id === (int) $user->id) {
                throw ValidationException::withMessages([
                    'status' => 'Người tạo phiếu không được tự hoàn tất phiếu (SoD).',
                ]);
            }
        }

        if (in_array($toStatus, ['DRAFT', 'PENDING'], true) && ! $user->hasAnyRole(['warehouse', 'manager', 'accountant'])) {
            throw ValidationException::withMessages([
                'status' => 'Bạn không có quyền chỉnh sửa phiếu nhập ở trạng thái này.',
            ]);
        }
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));

        if ($normalized === '') {
            return 'DRAFT';
        }

        if (! in_array($normalized, self::ALLOWED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Trạng thái phiếu nhập không hợp lệ.',
            ]);
        }

        return $normalized;
    }

    private function assertForwardTransition(string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        $fromRank = self::STATUS_RANK[$fromStatus] ?? null;
        $toRank = self::STATUS_RANK[$toStatus] ?? null;
        if (! is_int($fromRank) || ! is_int($toRank)) {
            throw ValidationException::withMessages([
                'status' => 'Không xác định được thứ tự trạng thái phiếu nhập.',
            ]);
        }

        if ($toRank <= $fromRank) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ được chuyển phiếu nhập sang trạng thái mới hơn.',
            ]);
        }

        if ($toStatus === 'CANCELLED' && $fromStatus !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'status' => 'Chỉ phiếu nhập đã hoàn tất mới được chuyển sang hủy.',
            ]);
        }
    }

    private function guardTenant(User $user, ImportReceipt $receipt): void
    {
        if ($receipt->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'receipt' => 'Import receipt does not belong to this tenant.',
            ]);
        }
    }
}
