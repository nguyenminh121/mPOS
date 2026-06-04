<?php

namespace App\Actions\Tenant\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Services\Tenant\CustomerService;

class DeleteCustomerAction
{
    public function __construct(private readonly CustomerService $customerService)
    {
    }

    public function execute(User $user, Customer $customer): void
    {
        $this->customerService->delete($user, $customer);
    }
}
