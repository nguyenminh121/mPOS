<?php

namespace App\Services\Tenant;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceListService
{
    public function list(User $user): Collection
    {
        return PriceList::query()
            ->where('tenant_id', $user->tenant_id)
            ->withCount(['items', 'customers', 'customerGroups'])
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'is_default',
                'is_active',
                'priority',
                'description',
                'start_date',
                'end_date',
            ]);
    }

    public function variantOptions(User $user): Collection
    {
        $variants = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->with([
                'product:id,name',
                'units.uom:id,name,symbol',
            ])
            ->orderBy('name')
            ->get(['id', 'product_id', 'name', 'sku', 'base_unit', 'price']);

        return $variants->map(function (ProductVariant $variant): array {
            $unitNames = collect([$variant->base_unit])
                ->merge($variant->units->map(fn ($unit) => $unit->uom?->name))
                ->filter(fn ($name) => is_string($name) && $name !== '')
                ->unique()
                ->values();

            return [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product?->name,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'base_unit' => $variant->base_unit,
                'default_price' => (float) $variant->price,
                'unit_names' => $unitNames,
            ];
        })->values();
    }

    public function create(User $user, array $data): PriceList
    {
        return DB::transaction(function () use ($user, $data): PriceList {
            if ((bool) Arr::get($data, 'is_default', false)) {
                $this->clearDefault($user);
            }

            $priceList = PriceList::query()->create([
                ...Arr::only($data, [
                    'code',
                    'name',
                    'is_default',
                    'is_active',
                    'priority',
                    'description',
                    'start_date',
                    'end_date',
                ]),
                'tenant_id' => $user->tenant_id,
                'created_by' => $user->id,
            ]);

            $items = collect(Arr::get($data, 'items', []))->values()->all();
            $this->syncItems($user, $priceList, $items);

            return $priceList->refresh();
        });
    }

    public function update(User $user, PriceList $priceList, array $data): PriceList
    {
        $this->guardTenant($user, $priceList);

        return DB::transaction(function () use ($user, $priceList, $data): PriceList {
            if ((bool) Arr::get($data, 'is_default', false)) {
                $this->clearDefault($user, (int) $priceList->id);
            }

            $priceList->fill(Arr::only($data, [
                'code',
                'name',
                'is_default',
                'is_active',
                'priority',
                'description',
                'start_date',
                'end_date',
            ]));
            $priceList->save();

            if (array_key_exists('items', $data)) {
                $items = collect(Arr::get($data, 'items', []))->values()->all();
                $this->syncItems($user, $priceList, $items);
            }

            return $priceList->refresh();
        });
    }

    public function delete(User $user, PriceList $priceList): void
    {
        $this->guardTenant($user, $priceList);

        $priceList->delete();
    }

    private function syncItems(User $user, PriceList $priceList, array $items): void
    {
        $normalized = collect($items)
            ->map(function (array $item): array {
                return [
                    'product_variant_id' => (int) $item['product_variant_id'],
                    'unit_name' => trim((string) ($item['unit_name'] ?? '')) ?: 'Cái',
                    'price' => (float) $item['price'],
                    'min_quantity' => max(1, (int) ($item['min_quantity'] ?? 1)),
                ];
            })
            ->unique(fn (array $item) => implode('|', [
                (string) $item['product_variant_id'],
                mb_strtolower($item['unit_name']),
                (string) $item['min_quantity'],
            ]))
            ->values();

        if ($normalized->isNotEmpty()) {
            $variantIds = $normalized->pluck('product_variant_id')->unique()->values();

            $validVariantCount = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->whereHas('product', fn ($query) => $query->where('tenant_id', $user->tenant_id))
                ->count();

            if ($validVariantCount !== $variantIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Có biến thể sản phẩm không thuộc tenant.',
                ]);
            }
        }

        PriceListItem::query()->where('price_list_id', $priceList->id)->delete();

        if ($normalized->isEmpty()) {
            return;
        }

        $payload = $normalized->map(fn (array $item) => [
            'price_list_id' => $priceList->id,
            'product_variant_id' => $item['product_variant_id'],
            'unit_name' => $item['unit_name'],
            'price' => $item['price'],
            'min_quantity' => $item['min_quantity'],
        ])->all();

        PriceListItem::query()->insert($payload);
    }

    private function clearDefault(User $user, ?int $exceptId = null): void
    {
        PriceList::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }

    private function guardTenant(User $user, PriceList $priceList): void
    {
        if ((int) $priceList->tenant_id !== (int) $user->tenant_id) {
            throw ValidationException::withMessages([
                'price_list' => 'Price list does not belong to this tenant.',
            ]);
        }
    }
}
