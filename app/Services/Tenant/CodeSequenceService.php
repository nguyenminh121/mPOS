<?php

namespace App\Services\Tenant;

use App\Models\CodeSequenceConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CodeSequenceService
{
    public function listForTenant(User $user)
    {
        return CodeSequenceConfig::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('entity_type')
            ->get();
    }

    public function upsert(User $user, array $data): CodeSequenceConfig
    {
        $payload = [
            'prefix' => $data['prefix'],
            'separator' => $data['separator'] ?? '-',
            'date_format' => $data['date_format'] ?? 'Ymd',
            'sequence_length' => $data['sequence_length'] ?? 6,
            'reset_period' => $data['reset_period'] ?? 'YEARLY',
            'sample_output' => $data['sample_output'] ?? null,
        ];

        return CodeSequenceConfig::query()->updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'entity_type' => strtoupper((string) $data['entity_type']),
            ],
            $payload,
        );
    }

    public function update(User $user, CodeSequenceConfig $config, array $data): CodeSequenceConfig
    {
        if ($config->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'config' => 'Code sequence configuration does not belong to this tenant.',
            ]);
        }

        $config->fill($data);
        $config->save();

        return $config->refresh();
    }

    public function delete(User $user, CodeSequenceConfig $config): void
    {
        if ($config->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'config' => 'Code sequence configuration does not belong to this tenant.',
            ]);
        }

        $config->delete();
    }

    public function nextCode(User $user, string $entityType): string
    {
        $config = CodeSequenceConfig::query()->firstOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'entity_type' => strtoupper($entityType),
            ],
            [
                'prefix' => strtoupper(substr($entityType, 0, 3)),
                'separator' => '-',
                'date_format' => 'Ymd',
                'sequence_length' => 6,
                'reset_period' => 'YEARLY',
                'current_value' => 0,
            ],
        );

        $today = Carbon::today();
        $lastResetAt = $config->last_reset_at ? Carbon::parse($config->last_reset_at) : null;
        $needsReset = false;

        if ($lastResetAt === null) {
            $needsReset = true;
        } elseif ($config->reset_period === 'DAILY') {
            $needsReset = ! $lastResetAt->isSameDay($today);
        } elseif ($config->reset_period === 'MONTHLY') {
            $needsReset = ! $lastResetAt->isSameMonth($today);
        } elseif ($config->reset_period === 'YEARLY') {
            $needsReset = ! $lastResetAt->isSameYear($today);
        }

        if ($needsReset) {
            $config->current_value = 0;
            $config->last_reset_at = $today;
        }

        $config->current_value = ((int) $config->current_value) + 1;
        $config->save();

        $prefix = (string) $config->prefix;
        $separator = (string) ($config->separator ?? '-');
        $datePart = $config->date_format ? Carbon::now()->format($config->date_format) : '';
        $sequence = str_pad((string) $config->current_value, (int) ($config->sequence_length ?? 6), '0', STR_PAD_LEFT);

        return trim(implode($separator, array_filter([$prefix, $datePart, $sequence], fn ($part) => $part !== '')));
    }
}
