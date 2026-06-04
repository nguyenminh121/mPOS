<?php

namespace App\Services\Tenant;

use App\Models\PaymentMethodConfig;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentMethodConfigService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function list(User $user): Collection
    {
        return PaymentMethodConfig::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function create(User $user, array $data): PaymentMethodConfig
    {
        return DB::transaction(function () use ($user, $data): PaymentMethodConfig {
            $payload = $this->normalizePayload($data);

            $paymentMethodConfig = PaymentMethodConfig::query()->create([
                ...$payload,
                'tenant_id' => (int) $user->tenant_id,
            ]);

            if ((bool) ($payload['is_default'] ?? false)) {
                $this->setDefault($user, $paymentMethodConfig);
                $paymentMethodConfig->refresh();
            }

            if (! PaymentMethodConfig::query()->where('tenant_id', $user->tenant_id)->where('is_default', true)->exists()) {
                $this->setDefault($user, $paymentMethodConfig);
                $paymentMethodConfig->refresh();
            }

            $this->recordAudit($user, 'PAYMENT_METHOD_CONFIG_CREATED', (int) $paymentMethodConfig->id, null, $paymentMethodConfig->toArray());

            return $paymentMethodConfig;
        });
    }

    public function update(User $user, PaymentMethodConfig $paymentMethodConfig, array $data): PaymentMethodConfig
    {
        $this->guardTenant($user, $paymentMethodConfig);

        return DB::transaction(function () use ($user, $paymentMethodConfig, $data): PaymentMethodConfig {
            $before = $paymentMethodConfig->toArray();
            $payload = $this->normalizePayload($data);

            $paymentMethodConfig->fill($payload);
            $paymentMethodConfig->save();

            if (array_key_exists('is_default', $payload) && (bool) $payload['is_default']) {
                $this->setDefault($user, $paymentMethodConfig);
                $paymentMethodConfig->refresh();
            }

            if ($paymentMethodConfig->is_default && ! $paymentMethodConfig->is_active) {
                throw ValidationException::withMessages([
                    'is_active' => 'Phương thức thanh toán mặc định phải ở trạng thái hoạt động.',
                ]);
            }

            if (! PaymentMethodConfig::query()->where('tenant_id', $user->tenant_id)->where('is_default', true)->exists()) {
                $fallback = PaymentMethodConfig::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                if ($fallback !== null) {
                    $this->setDefault($user, $fallback);
                }
            }

            $after = $paymentMethodConfig->refresh()->toArray();
            $this->recordAudit($user, 'PAYMENT_METHOD_CONFIG_UPDATED', (int) $paymentMethodConfig->id, $before, $after);

            return $paymentMethodConfig;
        });
    }

    public function delete(User $user, PaymentMethodConfig $paymentMethodConfig): void
    {
        $this->guardTenant($user, $paymentMethodConfig);

        DB::transaction(function () use ($user, $paymentMethodConfig): void {
            if ($paymentMethodConfig->is_default) {
                throw ValidationException::withMessages([
                    'payment_method_config' => 'Không thể xóa phương thức mặc định.',
                ]);
            }

            $before = $paymentMethodConfig->toArray();
            $paymentMethodConfig->delete();

            $this->recordAudit($user, 'PAYMENT_METHOD_CONFIG_DELETED', (int) $paymentMethodConfig->id, $before, null);
        });
    }

    private function setDefault(User $user, PaymentMethodConfig $paymentMethodConfig): void
    {
        $this->guardTenant($user, $paymentMethodConfig);

        PaymentMethodConfig::query()
            ->where('tenant_id', $user->tenant_id)
            ->update(['is_default' => false]);

        $paymentMethodConfig->forceFill(['is_default' => true, 'is_active' => true])->save();
    }

    private function normalizePayload(array $data): array
    {
        $payload = Arr::only($data, ['code', 'name', 'type', 'description', 'is_active', 'is_default', 'sort_order', 'config']);

        if (array_key_exists('code', $payload)) {
            $payload['code'] = strtoupper(trim((string) $payload['code']));
        }

        if (array_key_exists('type', $payload) && $payload['type'] !== null) {
            $payload['type'] = strtoupper(trim((string) $payload['type']));
        }

        return $payload;
    }

    private function guardTenant(User $user, PaymentMethodConfig $paymentMethodConfig): void
    {
        if ((int) $paymentMethodConfig->tenant_id !== (int) $user->tenant_id) {
            throw ValidationException::withMessages([
                'payment_method_config' => 'Payment method config does not belong to this tenant.',
            ]);
        }
    }

    private function recordAudit(User $user, string $action, ?int $recordId, mixed $oldValue, mixed $newValue): void
    {
        $this->auditLogService->record(
            user: $user,
            action: $action,
            tableName: 'payment_method_configs',
            recordId: $recordId,
            oldValue: $oldValue,
            newValue: $newValue,
        );
    }
}
