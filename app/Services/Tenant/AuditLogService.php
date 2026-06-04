<?php

namespace App\Services\Tenant;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogService
{
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $action = trim((string) Arr::get($filters, 'action', ''));
        $tableName = trim((string) Arr::get($filters, 'table_name', ''));
        $userId = Arr::get($filters, 'user_id');
        $fromDate = Arr::get($filters, 'from_date');
        $toDate = Arr::get($filters, 'to_date');

        if ($action !== '') {
            $query->where('action', 'like', '%'.$action.'%');
        }

        if ($tableName !== '') {
            $query->where('table_name', 'like', '%'.$tableName.'%');
        }

        if (is_numeric($userId) && (int) $userId > 0) {
            $query->where('user_id', (int) $userId);
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $perPage = max(1, min(200, (int) Arr::get($filters, 'per_page', 50)));

        return $query->paginate($perPage);
    }

    public function record(User $user, string $action, string $tableName, ?int $recordId = null, mixed $oldValue = null, mixed $newValue = null): AuditLog
    {
        return AuditLog::query()->create([
            'tenant_id' => (int) $user->tenant_id,
            'user_id' => (int) $user->id,
            'action' => strtoupper(trim($action)),
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_value' => $this->sanitizeValue($oldValue),
            'new_value' => $this->sanitizeValue($newValue),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public function recordModelEvent(string $event, Model $model): void
    {
        if (! config('audit.auto.enabled', true)) {
            return;
        }

        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();
        if (! $user instanceof User || ! is_numeric($user->tenant_id) || (int) $user->tenant_id <= 0) {
            return;
        }

        $tableName = (string) $model->getTable();
        if ($this->shouldSkipTable($tableName)) {
            return;
        }

        $recordId = is_numeric($model->getKey()) ? (int) $model->getKey() : null;

        if ($event === 'created') {
            $this->record(
                user: $user,
                action: 'MODEL_CREATED',
                tableName: $tableName,
                recordId: $recordId,
                oldValue: null,
                newValue: $model->attributesToArray(),
            );
            return;
        }

        if ($event === 'updated') {
            $changes = collect($model->getChanges())
                ->except(['updated_at'])
                ->all();

            if ($changes === []) {
                return;
            }

            $before = [];
            $previous = method_exists($model, 'getPrevious') ? (array) $model->getPrevious() : [];

            foreach ($changes as $key => $value) {
                $before[$key] = $previous[$key] ?? null;
            }

            $this->record(
                user: $user,
                action: 'MODEL_UPDATED',
                tableName: $tableName,
                recordId: $recordId,
                oldValue: $before,
                newValue: $changes,
            );
            return;
        }

        if ($event === 'deleted') {
            $this->record(
                user: $user,
                action: 'MODEL_DELETED',
                tableName: $tableName,
                recordId: $recordId,
                oldValue: $model->attributesToArray(),
                newValue: null,
            );
            return;
        }

        if ($event === 'restored') {
            $this->record(
                user: $user,
                action: 'MODEL_RESTORED',
                tableName: $tableName,
                recordId: $recordId,
                oldValue: null,
                newValue: $model->attributesToArray(),
            );
        }
    }

    private function shouldSkipTable(string $tableName): bool
    {
        $normalized = strtolower(trim($tableName));
        if ($normalized === '') {
            return true;
        }

        $excluded = collect(config('audit.auto.exclude_tables', ['audit_logs']))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->values();

        if ($excluded->contains($normalized)) {
            return true;
        }

        return str_ends_with($normalized, '_audits');
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $normalizedKey = strtolower((string) $key);
                if (in_array($normalizedKey, ['password', 'password_confirmation', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'], true)) {
                    continue;
                }

                $sanitized[$key] = $this->sanitizeValue($item);
            }

            return $sanitized;
        }

        if ($value instanceof \JsonSerializable) {
            return $this->sanitizeValue($value->jsonSerialize());
        }

        return (array) $value;
    }
}
