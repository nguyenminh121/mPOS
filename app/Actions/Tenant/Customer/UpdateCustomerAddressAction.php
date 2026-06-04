<?php

namespace App\Actions\Tenant\Customer;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Tenant\CustomerAddressService;

class UpdateCustomerAddressAction
{
    public function __construct(private readonly CustomerAddressService $customerAddressService)
    {
    }

    public function execute(User $user, Customer $customer, CustomerAddress $customerAddress, array $data): CustomerAddress
    {
        return $this->customerAddressService->update($user, $customer, $customerAddress, $data);
    }
}
