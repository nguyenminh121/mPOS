<?php

namespace App\Services\Tenant;

use App\Models\Feature;
use App\Models\TenantFeatureToggle;
use App\Models\User;

class InvoiceUsageConfigService
{
    public const FEATURE_CODE = 'USE_INVOICE_IN_SALES';
    public const FEATURE_STANDARD_MODE_CODE = 'INVOICE_MODE_STANDARD';
    public const FEATURE_POS_MODE_CODE = 'INVOICE_MODE_POS';
    public const FEATURE_BATCH_MODE_CODE = 'INVOICE_MODE_BATCH';

    public const MODE_STANDARD = 'STANDARD';
    public const MODE_POS = 'POS';
    public const MODE_BATCH = 'BATCH';

    public function getPolicy(User $user): array
    {
        return [
            'use_invoice_on_sale' => $this->useInvoiceOnSale($user),
            'invoice_mode' => $this->getInvoiceMode($user),
        ];
    }

    public function useInvoiceOnSale(User $user): bool
    {
        $feature = Feature::query()
            ->where('code', self::FEATURE_CODE)
            ->where('is_active', true)
            ->first();

        if (! $feature) {
            return true;
        }

        $enabled = TenantFeatureToggle::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('feature_id', $feature->id)
            ->value('is_enabled');

        if ($enabled === null) {
            return true;
        }

        return (bool) $enabled;
    }

    public function updatePolicy(User $user, bool $useInvoiceOnSale): void
    {
        $this->updatePolicyWithMode($user, $useInvoiceOnSale, self::MODE_POS);
    }

    public function updatePolicyWithMode(User $user, bool $useInvoiceOnSale, string $invoiceMode): void
    {
        $invoiceMode = strtoupper(trim($invoiceMode));
        if (! in_array($invoiceMode, [self::MODE_STANDARD, self::MODE_POS, self::MODE_BATCH], true)) {
            $invoiceMode = self::MODE_POS;
        }

        $feature = $this->upsertFeature(
            code: self::FEATURE_CODE,
            name: 'Use Invoice In Sales',
            description: 'Automatically issue invoices when sales are completed.',
        );

        $standardFeature = $this->upsertFeature(
            code: self::FEATURE_STANDARD_MODE_CODE,
            name: 'Invoice Mode Standard',
            description: 'Issue standard e-invoice in real-time.',
        );

        $posFeature = $this->upsertFeature(
            code: self::FEATURE_POS_MODE_CODE,
            name: 'Invoice Mode POS',
            description: 'Issue POS e-invoice in real-time at counter.',
        );

        $batchFeature = $this->upsertFeature(
            code: self::FEATURE_BATCH_MODE_CODE,
            name: 'Invoice Mode Batch',
            description: 'Batch issue invoices at end of day.',
        );

        TenantFeatureToggle::query()->updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'feature_id' => $feature->id,
            ],
            [
                'is_enabled' => $useInvoiceOnSale,
                'disabled_at' => $useInvoiceOnSale ? null : now(),
                'disabled_by' => $useInvoiceOnSale ? null : $user->id,
            ],
        );

        $this->setModeToggle($user, $standardFeature->id, $invoiceMode === self::MODE_STANDARD);
        $this->setModeToggle($user, $posFeature->id, $invoiceMode === self::MODE_POS);
        $this->setModeToggle($user, $batchFeature->id, $invoiceMode === self::MODE_BATCH);
    }

    public function getInvoiceMode(User $user): string
    {
        $featureCodes = [
            self::FEATURE_STANDARD_MODE_CODE => self::MODE_STANDARD,
            self::FEATURE_POS_MODE_CODE => self::MODE_POS,
            self::FEATURE_BATCH_MODE_CODE => self::MODE_BATCH,
        ];

        $features = Feature::query()
            ->whereIn('code', array_keys($featureCodes))
            ->where('is_active', true)
            ->get(['id', 'code'])
            ->keyBy('id');

        if ($features->isEmpty()) {
            return self::MODE_POS;
        }

        $enabledToggle = TenantFeatureToggle::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('feature_id', $features->keys()->all())
            ->where('is_enabled', true)
            ->orderByDesc('updated_at')
            ->first();

        if (! $enabledToggle) {
            return self::MODE_POS;
        }

        $featureCode = (string) ($features->get($enabledToggle->feature_id)?->code ?? '');

        return $featureCodes[$featureCode] ?? self::MODE_POS;
    }

    private function upsertFeature(string $code, string $name, string $description): Feature
    {
        return Feature::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'module' => 'SALES',
                'is_active' => true,
            ],
        );
    }

    private function setModeToggle(User $user, int $featureId, bool $isEnabled): void
    {
        TenantFeatureToggle::query()->updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'feature_id' => $featureId,
            ],
            [
                'is_enabled' => $isEnabled,
                'disabled_at' => $isEnabled ? null : now(),
                'disabled_by' => $isEnabled ? null : $user->id,
            ],
        );
    }
}
