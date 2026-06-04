<?php

namespace App\Actions\Tenant\CustomerGroup;

use App\Models\CustomerGroup;
use App\Models\User;
use App\Services\Tenant\CustomerGroupService;

class CreateCustomerGroupAction
{
    public function __construct(private readonly CustomerGroupService $customerGroupService)
    {
    }

    public function execute(User $user, array $data): CustomerGroup
    {
        return $this->customerGroupService->create($user, $data);
    }
}
