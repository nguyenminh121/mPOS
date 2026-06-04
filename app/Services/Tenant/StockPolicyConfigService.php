<?php

namespace App\Services\Tenant;

use App\Models\Feature;
use App\Models\TenantFeatureToggle;
use App\Models\User;

class StockPolicyConfigService
{
    public const FEATURE_CODE = 'ALLOW_NEGATIVE_STOCK';

    public function getPolicy(User $user): array
    {
        return [
            'allow_negative_stock' => $this->allowNegativeStock($user),
        ];
    }

    public function allowNegativeStock(User $user): bool
    {
        $feature = Feature::query()
            ->where('code', self::FEATURE_CODE)
            ->where('is_active', true)
            ->first();

        if (! $feature) {
            return false;
        }

        return (bool) TenantFeatureToggle::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('feature_id', $feature->id)
            ->value('is_enabled');
    }

    public function updatePolicy(User $user, bool $allowNegativeStock): void
    {
        $feature = Feature::query()->firstOrCreate(
            ['code' => self::FEATURE_CODE],
            [
                'name' => 'Allow Negative Stock',
                'description' => 'Allow stock balances to go negative when selling orders.',
                'module' => 'INVENTORY',
                'is_active' => true,
            ],
        );

        TenantFeatureToggle::query()->updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'feature_id' => $feature->id,
            ],
            [
                'is_enabled' => $allowNegativeStock,
                'disabled_at' => $allowNegativeStock ? null : now(),
                'disabled_by' => $allowNegativeStock ? null : $user->id,
            ],
        );
    }
}
