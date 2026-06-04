<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Uom;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->select(['id'])->chunkById(100, function ($tenants): void {
            foreach ($tenants as $tenant) {
                $this->seedForTenant((int) $tenant->id);
            }
        });
    }

    private function seedForTenant(int $tenantId): void
    {
        $cai = $this->upsertUom($tenantId, [
            'name' => 'CÁI',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_active' => true,
        ]);

        $g = $this->upsertUom($tenantId, [
            'name' => 'GAM',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_active' => true,
        ]);

        $ml = $this->upsertUom($tenantId, [
            'name' => 'MILILIT',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_active' => true,
        ]);

        $cm = $this->upsertUom($tenantId, [
            'name' => 'CENTIMET',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_active' => true,
        ]);

        $this->upsertUom($tenantId, [
            'name' => 'GÓI',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_active' => true,
        ]);

        $hop = $this->upsertUom($tenantId, [
            'name' => 'HỘP',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 10,
            'is_active' => true,
        ]);

        $this->upsertUom($tenantId, [
            'name' => 'THÙNG',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 20,
            'is_active' => true,
        ]);

        $lon = $this->upsertUom($tenantId, [
            'name' => 'LON',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 330,
            'is_active' => true,
        ]);

        $this->upsertUom($tenantId, [
            'name' => 'DÂY',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 6,
            'is_active' => true,
        ]);

        $this->upsertUom($tenantId, [
            'name' => 'CHAI',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 500,
            'is_active' => true,
        ]);

        $this->upsertUom($tenantId, [
            'name' => 'LÍT',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 1000,
            'is_active' => true,
        ]);

        $this->upsertUom($tenantId, [
            'name' => 'KILOGAM',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 1000,
            'is_active' => true,
        ]);

        $this->upsertUom($tenantId, [
            'name' => 'MÉT',
            'symbol' => null,
            'base_uom_id' => null,
            'conversion_factor' => 100,
            'is_active' => true,
        ]);
    }

    private function upsertUom(int $tenantId, array $attributes): Uom
    {
        return Uom::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => $attributes['name'],
            ],
            [
                'symbol' => $attributes['symbol'],
                'base_uom_id' => $attributes['base_uom_id'],
                'conversion_factor' => $attributes['conversion_factor'],
                'is_active' => $attributes['is_active'],
            ],
        );
    }
}
