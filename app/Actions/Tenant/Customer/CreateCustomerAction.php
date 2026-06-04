<?php

namespace App\Actions\Tenant\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Services\Tenant\CustomerService;

class CreateCustomerAction
{
    public function __construct(private readonly CustomerService $customerService)
    {
    }

    public function execute(User $user, array $data): Customer
    {
        return $this->customerService->create($user, $data);
    }
}
