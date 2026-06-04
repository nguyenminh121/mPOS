<?php

namespace App\Actions\Tenant\CustomerGroup;

use App\Models\CustomerGroup;
use App\Models\User;
use App\Services\Tenant\CustomerGroupService;

class UpdateCustomerGroupAction
{
    public function __construct(private readonly CustomerGroupService $customerGroupService)
    {
    }

    public function execute(User $user, CustomerGroup $customerGroup, array $data): CustomerGroup
    {
        return $this->customerGroupService->update($user, $customerGroup, $data);
    }
}
