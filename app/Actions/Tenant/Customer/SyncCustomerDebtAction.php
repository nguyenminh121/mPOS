<?php

namespace App\Actions\Tenant\Customer;

use App\Models\User;
use App\Services\Tenant\CustomerDebtService;

class SyncCustomerDebtAction
{
    public function __construct(private readonly CustomerDebtService $customerDebtService)
    {
    }

    public function execute(User $user, ?int $customerId = null): void
    {
        if (is_numeric($customerId) && (int) $customerId > 0) {
            $this->customerDebtService->syncForCustomer((int) $user->tenant_id, (int) $customerId);
            return;
        }

        $this->customerDebtService->syncForTenant((int) $user->tenant_id);
    }
}
