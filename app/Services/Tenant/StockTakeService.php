<?php

namespace App\Services\Tenant;

use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTakeService
{
    public function __construct(
        private readonly CodeSequenceService $codeSequenceService,
    ) {
    }

    public function list(User $user): Collection
    {
        return StockTake::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['warehouse:id,name', 'creator:id,name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->get();
    }

    public function detail(User $user, StockTake $stockTake): StockTake
    {
        $this->guardTenant($user, $stockTake);

        $stockTake->load([
            'warehouse:id,name',
            'creator:id,name',
            'items.productVariant:id,product_id,name,sku,barcode,base_unit',
            'items.productVariant.product:id,name',
            'items.productVariant.units:id,product_variant_id,uom_id,conversion_factor',
            'items.productVariant.units.uom:id,name,symbol',
            'stockMovements',
        ]);

        return $stockTake;
    }

    public function create(User $user, array $data): StockTake
    {
        return DB::transaction(function () use ($user, $data): StockTake {
            $warehouseId = (int) Arr::get($data, 'warehouse_id');
            $status = strtoupper((string) Arr::get($data, 'status', 'DRAFT'));

            $stockTake = StockTake::query()->create([
                'tenant_id' => (int) $user->tenant_id,
                'code' => $this->codeSequenceService->nextCode($user, 'STOCK_TAKE'),
                'warehouse_id' => $warehouseId,
                'creator_id' => (int) $user->id,
                'status' => in_array($status, ['DRAFT', 'IN_PROGRESS'], true) ? $status : 'IN_PROGRESS',
                'note' => Arr::get($data, 'note'),
                'completed_at' => null,
            ]);

            $this->syncItems($user, $stockTake, Arr::get($data, 'items', []));

            return $stockTake->refresh()->load(['warehouse:id,name', 'creator:id,name', 'items.productVariant.product:id,name']);
        });
    }

    public function update(User $user, StockTake $stockTake, array $data): StockTake
    {
        $this->guardTenant($user, $stockTake);

        return DB::transaction(function () use ($user, $stockTake, $data): StockTake {
            $status = strtoupper((string) $stockTake->status);
            if (in_array($status, ['COMPLETED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages([
                    'stock_take' => 'Phiếu đã hoàn tất hoặc đã hủy, không thể chỉnh sửa.',
                ]);
            }

            $incomingStatus = Arr::get($data, 'status');
            if (is_string($incomingStatus) && $incomingStatus !== '') {
                $incomingStatus = strtoupper($incomingStatus);
                if (in_array($incomingStatus, ['DRAFT', 'IN_PROGRESS', 'CANCELLED'], true)) {
                    $stockTake->status = $incomingStatus;
                }
            }

            if (array_key_exists('warehouse_id', $data)) {
                $stockTake->warehouse_id = (int) Arr::get($data, 'warehouse_id');
            }

            if (array_key_exists('note', $data)) {
                $stockTake->note = Arr::get($data, 'note');
            }

            $stockTake->save();

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->syncItems($user, $stockTake, $data['items']);
            }

            return $stockTake->refresh()->load(['warehouse:id,name', 'creator:id,name', 'items.productVariant.product:id,name']);
        });
    }

    public function delete(User $user, StockTake $stockTake): void
    {
        $this->guardTenant($user, $stockTake);

        DB::transaction(function () use ($stockTake): void {
            if (strtoupper((string) $stockTake->status) === 'COMPLETED') {
                throw ValidationException::withMessages([
                    'stock_take' => 'Phiếu đã hoàn tất không thể xóa trực tiếp. Vui lòng undo trước.',
                ]);
            }

            $stockTake->items()->delete();
            $stockTake->delete();
        });
    }

    public function complete(User $user, StockTake $stockTake): StockTake
    {
        $this->guardTenant($user, $stockTake);

        return DB::transaction(function () use ($user, $stockTake): StockTake {
            if (strtoupper((string) $stockTake->status) === 'COMPLETED') {
                return $stockTake->refresh();
            }

            $stockTake->loadMissing('items');
            if ($stockTake->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Phiếu kiểm kê chưa có sản phẩm.',
                ]);
            }

            $this->applyAdjustments($user, $stockTake);

            $stockTake->status = 'COMPLETED';
            $stockTake->completed_at = now();
            $stockTake->save();

            return $this->detail($user, $stockTake->refresh());
        });
    }

    public function undo(User $user, StockTake $stockTake): StockTake
    {
        $this->guardTenant($user, $stockTake);

        return DB::transaction(function () use ($user, $stockTake): StockTake {
            if (strtoupper((string) $stockTake->status) !== 'COMPLETED') {
                throw ValidationException::withMessages([
                    'stock_take' => 'Chỉ có thể undo phiếu đã hoàn tất.',
                ]);
            }

            $movements = StockMovement::query()
                ->where('stock_take_id', $stockTake->id)
                ->orderByDesc('id')
                ->get();

            foreach ($movements as $movement) {
                $reverseDelta = $movement->movement_type === 'ADJUST_IN'
                    ? -((int) $movement->quantity)
                    : (int) $movement->quantity;

                $stock = Stock::query()->firstOrCreate(
                    [
                        'product_variant_id' => (int) $movement->product_variant_id,
                        'warehouse_id' => (int) $movement->warehouse_id,
                    ],
                    [
                        'quantity' => 0,
                        'warehouse_location_id' => null,
                    ],
                );

                $newBalance = (int) $stock->quantity + $reverseDelta;
                if ($newBalance < 0) {
                    throw ValidationException::withMessages([
                        'stock_take' => 'Không thể undo vì tồn kho hiện tại đã bị sử dụng bởi giao dịch khác.',
                    ]);
                }

                $stock->quantity = $newBalance;
                $stock->save();

                StockMovement::query()->create([
                    'product_variant_id' => (int) $movement->product_variant_id,
                    'warehouse_id' => (int) $movement->warehouse_id,
                    'batch_id' => null,
                    'movement_type' => $reverseDelta >= 0 ? 'ADJUST_IN' : 'ADJUST_OUT',
                    'quantity' => abs($reverseDelta),
                    'balance_after' => $newBalance,
                    'cost_price_at_move' => $movement->cost_price_at_move,
                    'import_item_id' => null,
                    'stock_take_id' => null,
                    'note' => "Undo phiếu kiểm kê {$stockTake->code}",
                    'created_by' => $user->id,
                    'created_at' => now(),
                ]);
            }

            StockMovement::query()
                ->where('stock_take_id', $stockTake->id)
                ->delete();

            $stockTake->status = 'CANCELLED';
            $stockTake->completed_at = null;
            $stockTake->save();

            return $this->detail($user, $stockTake->refresh());
        });
    }

    private function syncItems(User $user, StockTake $stockTake, array $items): void
    {
        $existingIds = $stockTake->items()->pluck('id')->all();
        $incomingIds = collect($items)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $idsToDelete = array_diff($existingIds, $incomingIds);
        if (! empty($idsToDelete)) {
            $stockTake->items()->whereIn('id', $idsToDelete)->delete();
        }

        $variantIds = collect($items)
            ->pluck('product_variant_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validVariantIds = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->pluck('id')
            ->all();

        $variants = ProductVariant::query()
            ->whereIn('id', $validVariantIds)
            ->with(['units:id,product_variant_id,uom_id,conversion_factor', 'units.uom:id,name'])
            ->get()
            ->keyBy('id');

        $stockByVariant = Stock::query()
            ->where('warehouse_id', $stockTake->warehouse_id)
            ->whereIn('product_variant_id', $variantIds)
            ->get()
            ->keyBy('product_variant_id');

        foreach ($items as $item) {
            $variantId = (int) Arr::get($item, 'product_variant_id');
            if (! in_array($variantId, $validVariantIds, true)) {
                throw ValidationException::withMessages([
                    'items' => 'Có sản phẩm không thuộc tenant hoặc không hợp lệ.',
                ]);
            }

            $variant = $variants->get($variantId);
            if (! $variant instanceof ProductVariant) {
                throw ValidationException::withMessages([
                    'items' => 'Không tìm thấy cấu hình đơn vị tính của sản phẩm kiểm kê.',
                ]);
            }

            $countedQty = (float) Arr::get($item, 'counted_qty', Arr::get($item, 'actual_qty', 0));
            $countConversionRate = max(1, (int) Arr::get($item, 'count_conversion_rate', 1));
            $countUomName = trim((string) Arr::get($item, 'count_uom_name', ''));

            $allowedUnits = $this->allowedCountUnits($variant);

            if ($countUomName !== '') {
                if (! array_key_exists($countUomName, $allowedUnits)) {
                    throw ValidationException::withMessages([
                        'items' => "Đơn vị kiểm kê '{$countUomName}' không hợp lệ với sản phẩm được chọn.",
                    ]);
                }

                if ((int) $allowedUnits[$countUomName] !== $countConversionRate) {
                    throw ValidationException::withMessages([
                        'items' => "Tỷ lệ quy đổi cho đơn vị '{$countUomName}' không khớp cấu hình sản phẩm.",
                    ]);
                }
            } elseif ($countConversionRate !== 1) {
                throw ValidationException::withMessages([
                    'items' => 'Thiếu tên đơn vị kiểm kê cho tỷ lệ quy đổi đã chọn.',
                ]);
            }

            $actualQty = $this->toBaseQuantity($countedQty, $countConversionRate);
            $systemQty = (int) ($stockByVariant->get($variantId)?->quantity ?? 0);
            $diffQty = $actualQty - $systemQty;

            $payload = [
                'product_variant_id' => $variantId,
                'system_qty' => $systemQty,
                'actual_qty' => $actualQty,
                'diff_qty' => $diffQty,
                'reason' => Arr::get($item, 'reason'),
            ];

            if (! empty($item['id'])) {
                $updated = $stockTake->items()->where('id', (int) $item['id'])->update($payload);
                if ($updated === 0) {
                    $stockTake->items()->create($payload);
                }
            } else {
                $stockTake->items()->create($payload);
            }
        }
    }

    private function allowedCountUnits(ProductVariant $variant): array
    {
        $allowed = [];

        $baseName = trim((string) ($variant->base_unit ?? ''));
        if ($baseName !== '') {
            $allowed[$baseName] = 1;
        }

        foreach ($variant->units as $unit) {
            $unitName = trim((string) ($unit->uom?->name ?? ''));
            if ($unitName === '') {
                continue;
            }

            $factor = (int) $unit->conversion_factor;
            if ($factor <= 0) {
                continue;
            }

            $allowed[$unitName] = $factor;
        }

        return $allowed;
    }

    private function toBaseQuantity(float $countedQty, int $conversionRate): int
    {
        if ($countedQty < 0) {
            throw ValidationException::withMessages([
                'items' => 'Số lượng kiểm kê không được âm.',
            ]);
        }

        if ($conversionRate <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Tỷ lệ quy đổi không hợp lệ.',
            ]);
        }

        $actualRaw = $countedQty * $conversionRate;
        $actualRounded = (int) round($actualRaw);

        if (abs($actualRaw - $actualRounded) > 0.0001) {
            throw ValidationException::withMessages([
                'items' => 'Số lượng quy đổi về đơn vị gốc phải là số nguyên.',
            ]);
        }

        return max(0, $actualRounded);
    }

    private function applyAdjustments(User $user, StockTake $stockTake): void
    {
        $alreadyPosted = StockMovement::query()
            ->where('stock_take_id', $stockTake->id)
            ->exists();

        if ($alreadyPosted) {
            throw ValidationException::withMessages([
                'stock_take' => 'Phiếu đã có bút toán điều chỉnh. Vui lòng undo trước khi thực hiện lại.',
            ]);
        }

        $stockTake->loadMissing('items.productVariant');

        foreach ($stockTake->items as $item) {
            $diffQty = (int) $item->diff_qty;
            if ($diffQty === 0) {
                continue;
            }

            $stock = Stock::query()->firstOrCreate(
                [
                    'product_variant_id' => (int) $item->product_variant_id,
                    'warehouse_id' => (int) $stockTake->warehouse_id,
                ],
                [
                    'quantity' => 0,
                    'warehouse_location_id' => null,
                ],
            );

            $newBalance = (int) $stock->quantity + $diffQty;
            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'stock_take' => 'Số lượng kiểm kê làm tồn kho âm, vui lòng kiểm tra lại.',
                ]);
            }

            $stock->quantity = $newBalance;
            $stock->save();

            StockMovement::query()->create([
                'product_variant_id' => (int) $item->product_variant_id,
                'warehouse_id' => (int) $stockTake->warehouse_id,
                'batch_id' => null,
                'movement_type' => $diffQty > 0 ? 'ADJUST_IN' : 'ADJUST_OUT',
                'quantity' => abs($diffQty),
                'balance_after' => $newBalance,
                'cost_price_at_move' => $item->productVariant?->cost_price,
                'order_id' => null,
                'import_item_id' => null,
                'stock_take_id' => (int) $stockTake->id,
                'note' => "Điều chỉnh từ phiếu kiểm kê {$stockTake->code}",
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }
    }

    private function guardTenant(User $user, StockTake $stockTake): void
    {
        if ((int) $user->tenant_id !== (int) $stockTake->tenant_id) {
            throw ValidationException::withMessages([
                'stock_take' => 'Phiếu kiểm kê không thuộc tenant hiện tại.',
            ]);
        }
    }
}
