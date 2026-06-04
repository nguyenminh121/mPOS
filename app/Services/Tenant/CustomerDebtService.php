<?php

namespace App\Services\Tenant;

use App\Models\AccountReceivable;
use App\Models\Customer;
use Illuminate\Support\Collection;

class CustomerDebtService
{
    public function syncForTenant(int $tenantId): void
    {
        $debtByCustomer = AccountReceivable::query()
            ->where('tenant_id', $tenantId)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(amount) as outstanding')
            ->pluck('outstanding', 'customer_id')
            ->map(fn ($value): float => max(0, round((float) $value, 2)));

        Customer::query()
            ->where('tenant_id', $tenantId)
            ->update(['debt_amount' => 0]);

        foreach ($debtByCustomer as $customerId => $outstanding) {
            if (! is_numeric($customerId) || (int) $customerId <= 0) {
                continue;
            }

            Customer::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((int) $customerId)
                ->update(['debt_amount' => $outstanding]);
        }
    }

    public function syncForCustomer(int $tenantId, ?int $customerId): void
    {
        if (! is_numeric($customerId) || (int) $customerId <= 0) {
            return;
        }

        $outstanding = (float) AccountReceivable::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', (int) $customerId)
            ->sum('amount');

        Customer::query()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) $customerId)
            ->update(['debt_amount' => max(0, round($outstanding, 2))]);
    }

    public function mapOutstandingByCustomer(int $tenantId): Collection
    {
        return AccountReceivable::query()
            ->where('tenant_id', $tenantId)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(amount) as outstanding')
            ->pluck('outstanding', 'customer_id')
            ->map(fn ($value): float => max(0, round((float) $value, 2)));
    }
}
