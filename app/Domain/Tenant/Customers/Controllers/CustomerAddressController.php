<?php

namespace App\Domain\Tenant\Customers\Controllers;

use App\Actions\Tenant\Customer\CreateCustomerAddressAction;
use App\Actions\Tenant\Customer\DeleteCustomerAddressAction;
use App\Actions\Tenant\Customer\UpdateCustomerAddressAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerAddressRequest;
use App\Http\Requests\Customer\UpdateCustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly CreateCustomerAddressAction $createCustomerAddressAction,
        private readonly UpdateCustomerAddressAction $updateCustomerAddressAction,
        private readonly DeleteCustomerAddressAction $deleteCustomerAddressAction,
    ) {
    }

    public function store(StoreCustomerAddressRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->createCustomerAddressAction->execute($request->user(), $customer, $request->validated());

        return back()->with('success', 'Đã thêm địa chỉ khách hàng.');
    }

    public function update(UpdateCustomerAddressRequest $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->authorize('update', $address);

        $this->updateCustomerAddressAction->execute($request->user(), $customer, $address, $request->validated());

        return back()->with('success', 'Đã cập nhật địa chỉ khách hàng.');
    }

    public function destroy(Request $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->authorize('delete', $address);

        $this->deleteCustomerAddressAction->execute($request->user(), $customer, $address);

        return back()->with('success', 'Đã xóa địa chỉ khách hàng.');
    }
}

