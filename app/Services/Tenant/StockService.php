<?php

namespace App\Services\Tenant;

use App\Models\Stock;
use Illuminate\Support\Collection;

class StockService
{
    public function list(): Collection
    {
        return Stock::query()
            ->with([
                'warehouse:id,name',
                'productVariant:id,product_id,name,sku',
                'productVariant.product:id,name',
            ])
            ->orderByDesc('id')
            ->get();
    }

    public function detail(Stock $stock): Stock
    {
        $stock->load([
            'warehouse:id,name',
            'productVariant:id,product_id,name,sku,price,cost_price,wholesale_price',
            'productVariant.product:id,name',
            'productVariant.stockBatches' => fn ($query) => $query
                ->where('warehouse_id', $stock->warehouse_id)
                ->orderByDesc('received_at'),
            'productVariant.stockMovements' => fn ($query) => $query
                ->where('warehouse_id', $stock->warehouse_id)
                ->latest('created_at')
                ->limit(100),
        ]);

        return $stock;
    }
}
