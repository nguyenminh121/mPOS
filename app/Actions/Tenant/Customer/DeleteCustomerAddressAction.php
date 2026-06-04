<?php

namespace App\Actions\Tenant\Customer;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Tenant\CustomerAddressService;

class DeleteCustomerAddressAction
{
    public function __construct(private readonly CustomerAddressService $customerAddressService)
    {
    }

    public function execute(User $user, Customer $customer, CustomerAddress $customerAddress): void
    {
        $this->customerAddressService->delete($user, $customer, $customerAddress);
    }
}
