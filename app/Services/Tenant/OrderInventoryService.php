<?php

namespace App\Services\Tenant;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderInventoryService
{
    public function deductForOrder(User $user, Order $order, bool $allowNegativeStock): void
    {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return;
        }

        $requiredByVariant = $this->collectRequiredBaseQuantities($order);
        $variantNames = ProductVariant::query()
            ->whereIn('id', $requiredByVariant->keys()->all())
            ->pluck('name', 'id');

        $stocks = Stock::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->whereIn('product_variant_id', $requiredByVariant->keys()->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');

        foreach ($requiredByVariant as $variantId => $requiredBaseQty) {
            $currentStock = (int) ($stocks->get($variantId)?->quantity ?? 0);
            if (! $allowNegativeStock && $currentStock < $requiredBaseQty) {
                $variantName = (string) ($variantNames->get($variantId) ?? ('Variant #' . $variantId));

                throw ValidationException::withMessages([
                    'items' => "Tồn kho không đủ cho {$variantName}. Cần {$requiredBaseQty}, hiện có {$currentStock}.",
                ]);
            }
        }

        foreach ($requiredByVariant as $variantId => $requiredBaseQty) {
            $stock = Stock::query()->firstOrCreate(
                [
                    'product_variant_id' => $variantId,
                    'warehouse_id' => (int) $order->warehouse_id,
                ],
                [
                    'warehouse_location_id' => null,
                    'quantity' => 0,
                ],
            );

            $newBalance = (int) $stock->quantity - $requiredBaseQty;
            $stock->quantity = $newBalance;
            $stock->save();

            StockMovement::query()->create([
                'product_variant_id' => $variantId,
                'warehouse_id' => (int) $order->warehouse_id,
                'batch_id' => null,
                'movement_type' => 'EXPORT',
                'quantity' => $requiredBaseQty,
                'balance_after' => $newBalance,
                'cost_price_at_move' => null,
                'order_id' => (int) $order->id,
                'import_item_id' => null,
                'stock_take_id' => null,
                'note' => "Xuất kho từ đơn bán {$order->code}",
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }
    }

    public function reconcileForOrderUpdate(User $user, Order $order, array $previousRequiredByVariant, bool $allowNegativeStock, ?string $note = null): void
    {
        $order->loadMissing('items');

        $currentRequiredByVariant = $this->collectRequiredBaseQuantities($order)
            ->mapWithKeys(fn (int $qty, int $variantId) => [(int) $variantId => $qty])
            ->all();

        $variantIds = array_values(array_unique(array_map(
            fn ($id) => (int) $id,
            array_merge(array_keys($previousRequiredByVariant), array_keys($currentRequiredByVariant)),
        )));

        if (count($variantIds) === 0) {
            return;
        }

        $variantNames = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->pluck('name', 'id');

        $stocks = Stock::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->whereIn('product_variant_id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');

        foreach ($variantIds as $variantId) {
            $previousQty = (int) ($previousRequiredByVariant[$variantId] ?? 0);
            $currentQty = (int) ($currentRequiredByVariant[$variantId] ?? 0);
            $delta = $currentQty - $previousQty;

            if ($delta <= 0) {
                continue;
            }

            $currentStock = (int) ($stocks->get($variantId)?->quantity ?? 0);
            if (! $allowNegativeStock && $currentStock < $delta) {
                $variantName = (string) ($variantNames->get($variantId) ?? ('Variant #' . $variantId));

                throw ValidationException::withMessages([
                    'items' => "Tồn kho không đủ cho {$variantName}. Cần {$delta}, hiện có {$currentStock}.",
                ]);
            }
        }

        foreach ($variantIds as $variantId) {
            $previousQty = (int) ($previousRequiredByVariant[$variantId] ?? 0);
            $currentQty = (int) ($currentRequiredByVariant[$variantId] ?? 0);
            $delta = $currentQty - $previousQty;

            if ($delta === 0) {
                continue;
            }

            $stock = Stock::query()->firstOrCreate(
                [
                    'product_variant_id' => $variantId,
                    'warehouse_id' => (int) $order->warehouse_id,
                ],
                [
                    'warehouse_location_id' => null,
                    'quantity' => 0,
                ],
            );

            $newBalance = (int) $stock->quantity - $delta;
            $stock->quantity = $newBalance;
            $stock->save();

            StockMovement::query()->create([
                'product_variant_id' => $variantId,
                'warehouse_id' => (int) $order->warehouse_id,
                'batch_id' => null,
                'movement_type' => $delta > 0 ? 'ADJUST_OUT' : 'ADJUST_IN',
                'quantity' => abs($delta),
                'balance_after' => $newBalance,
                'cost_price_at_move' => null,
                'order_id' => (int) $order->id,
                'import_item_id' => null,
                'stock_take_id' => null,
                'note' => $note ?: "Điều chỉnh tồn kho theo cập nhật đơn {$order->code}",
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }
    }

    private function collectRequiredBaseQuantities(Order $order): Collection
    {
        return $order->items
            ->groupBy('product_variant_id')
            ->map(function (Collection $rows): int {
                return (int) $rows->sum(function ($item) {
                    $quantity = (int) ($item->quantity ?? 0);
                    $conversionRate = max(1, (int) ($item->conversion_rate ?? 1));

                    return $quantity * $conversionRate;
                });
            })
            ->filter(fn (int $qty) => $qty > 0);
    }
}
