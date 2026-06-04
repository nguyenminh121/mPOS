<?php

namespace App\Services\Tenant;

use App\Models\Uom;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UomService
{
    public function list(User $user): Collection
    {
        return Uom::query()
            ->where('tenant_id', $user->tenant_id)
            ->with('baseUom:id,name,symbol')
            ->withCount(['derivedUoms', 'productUnits', 'productPrices'])
            ->orderBy('name')
            ->get(['id', 'name', 'symbol', 'base_uom_id', 'is_active']);
    }

    public function baseOptions(User $user, ?Uom $excluded = null): Collection
    {
        $query = Uom::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name');

        if ($excluded !== null) {
            $query->whereKeyNot($excluded->id);
        }

        return $query->get(['id', 'name', 'symbol']);
    }

    public function create(User $user, array $data): Uom
    {
        $payload = $this->normalizePayload($data);

        $this->assertUniqueNormalizedName($user, $payload['name']);
        $this->assertUniqueNormalizedSymbol($user, $payload['symbol']);
        $this->assertBaseBelongsToTenant($user, $payload['base_uom_id']);

        return Uom::query()->create([
            ...$payload,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Uom $uom, array $data): Uom
    {
        $this->guardTenant($user, $uom);

        $payload = $this->normalizePayload($data, true);

        if (array_key_exists('name', $payload)) {
            $this->assertUniqueNormalizedName($user, $payload['name'], (int) $uom->id);
        }

        if (array_key_exists('symbol', $payload)) {
            $this->assertUniqueNormalizedSymbol($user, $payload['symbol'], (int) $uom->id);
        }

        if (array_key_exists('base_uom_id', $payload)) {
            if ($payload['base_uom_id'] !== null && (int) $payload['base_uom_id'] === (int) $uom->id) {
                throw ValidationException::withMessages([
                    'base_uom_id' => 'Đơn vị gốc không hợp lệ.',
                ]);
            }

            $this->assertBaseBelongsToTenant($user, $payload['base_uom_id']);
        }

        $uom->fill($payload);
        $uom->save();

        return $uom->refresh();
    }

    public function delete(User $user, Uom $uom): void
    {
        $this->guardTenant($user, $uom);

        if ($uom->derivedUoms()->exists()) {
            throw ValidationException::withMessages([
                'uom' => 'Không thể xóa đơn vị tính đang có đơn vị quy đổi phụ thuộc.',
            ]);
        }

        if ($uom->productUnits()->exists() || $uom->productPrices()->exists()) {
            throw ValidationException::withMessages([
                'uom' => 'Không thể xóa đơn vị tính đang được sử dụng trong sản phẩm.',
            ]);
        }

        $uom->delete();
    }

    private function normalizePayload(array $data, bool $partial = false): array
    {
        $payload = Arr::only($data, ['name', 'symbol', 'base_uom_id', 'is_active']);

        if (array_key_exists('name', $payload) || ! $partial) {
            $payload['name'] = $this->normalizeText((string) ($payload['name'] ?? ''));
        }

        if (array_key_exists('symbol', $payload) || ! $partial) {
            $symbol = $this->normalizeText((string) ($payload['symbol'] ?? ''));
            $payload['symbol'] = $symbol === '' ? null : $symbol;
        }

        if (array_key_exists('base_uom_id', $payload) || ! $partial) {
            $base = $payload['base_uom_id'] ?? null;
            $payload['base_uom_id'] = $base === null || $base === '' ? null : (int) $base;
        }

        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = (bool) $payload['is_active'];
        }

        return $payload;
    }

    private function normalizeText(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $singleSpaced = preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;

        return mb_strtoupper($singleSpaced, 'UTF-8');
    }

    private function assertUniqueNormalizedName(User $user, string $normalizedName, ?int $ignoreId = null): void
    {
        $exists = Uom::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'name'])
            ->contains(fn (Uom $uom) => $this->normalizeText((string) $uom->name) === $normalizedName);

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Tên đơn vị tính đã tồn tại trong tenant.',
            ]);
        }
    }

    private function assertUniqueNormalizedSymbol(User $user, ?string $normalizedSymbol, ?int $ignoreId = null): void
    {
        if ($normalizedSymbol === null || $normalizedSymbol === '') {
            return;
        }

        $exists = Uom::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereNotNull('symbol')
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'symbol'])
            ->contains(fn (Uom $uom) => $this->normalizeText((string) $uom->symbol) === $normalizedSymbol);

        if ($exists) {
            throw ValidationException::withMessages([
                'symbol' => 'Ký hiệu đơn vị tính đã tồn tại trong tenant.',
            ]);
        }
    }

    private function assertBaseBelongsToTenant(User $user, ?int $baseUomId): void
    {
        if ($baseUomId === null) {
            return;
        }

        $exists = Uom::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($baseUomId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'base_uom_id' => 'Đơn vị gốc không tồn tại trong tenant.',
            ]);
        }
    }

    private function guardTenant(User $user, Uom $uom): void
    {
        if ($uom->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'uom' => 'UOM does not belong to this tenant.',
            ]);
        }
    }
}