<?php

namespace App\Domain\Tenant\Customers\Controllers;

use App\Actions\Tenant\CustomerGroup\CreateCustomerGroupAction;
use App\Actions\Tenant\CustomerGroup\DeleteCustomerGroupAction;
use App\Actions\Tenant\CustomerGroup\UpdateCustomerGroupAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerGroupRequest;
use App\Http\Requests\Customer\UpdateCustomerGroupRequest;
use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Services\Tenant\CustomerGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function __construct(
        private readonly CustomerGroupService $customerGroupService,
        private readonly CreateCustomerGroupAction $createCustomerGroupAction,
        private readonly UpdateCustomerGroupAction $updateCustomerGroupAction,
        private readonly DeleteCustomerGroupAction $deleteCustomerGroupAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerGroup::class);

        return Inertia::render('tenant/customer-groups/Index', [
            'customerGroups' => $this->customerGroupService->list($request->user())->map(fn (CustomerGroup $group) => $this->toPayload($group))->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', CustomerGroup::class);

        $user = $request->user();

        return Inertia::render('tenant/customer-groups/Form', [
            'mode' => 'create',
            'customerGroup' => null,
            'priceLists' => PriceList::query()->where('tenant_id', $user->tenant_id)->orderByDesc('is_default')->orderByDesc('priority')->orderBy('name')->get(['id', 'code', 'name', 'is_default']),
        ]);
    }

    public function show(CustomerGroup $customerGroup): Response
    {
        $this->authorize('view', $customerGroup);

        $customerGroup->load(['defaultPriceList:id,name,code,is_default']);
        $customerGroup->loadCount('customers');

        return Inertia::render('tenant/customer-groups/Show', [
            'customerGroup' => $this->toPayload($customerGroup),
        ]);
    }

    public function store(StoreCustomerGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', CustomerGroup::class);

        $this->createCustomerGroupAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.customer-groups.index')->with('success', 'Tạo nhóm khách hàng thành công.');
    }

    public function edit(Request $request, CustomerGroup $customerGroup): Response
    {
        $this->authorize('update', $customerGroup);

        $user = $request->user();
        $customerGroup->load(['defaultPriceList:id,name,code,is_default']);
        $customerGroup->loadCount('customers');

        return Inertia::render('tenant/customer-groups/Form', [
            'mode' => 'edit',
            'customerGroup' => $this->toPayload($customerGroup),
            'priceLists' => PriceList::query()->where('tenant_id', $user->tenant_id)->orderByDesc('is_default')->orderByDesc('priority')->orderBy('name')->get(['id', 'code', 'name', 'is_default']),
        ]);
    }

    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $this->authorize('update', $customerGroup);

        $this->updateCustomerGroupAction->execute($request->user(), $customerGroup, $request->validated());

        return redirect()->route('tenant.customer-groups.index')->with('success', 'Cập nhật nhóm khách hàng thành công.');
    }

    public function destroy(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $this->authorize('delete', $customerGroup);

        $this->deleteCustomerGroupAction->execute($request->user(), $customerGroup);

        return redirect()->route('tenant.customer-groups.index')->with('success', 'Xóa nhóm khách hàng thành công.');
    }

    private function toPayload(CustomerGroup $group): array
    {
        return [
            'id' => $group->id,
            'code' => $group->code,
            'name' => $group->name,
            'description' => $group->description,
            'default_price_list_id' => $group->default_price_list_id,
            'is_active' => (bool) $group->is_active,
            'customers_count' => (int) ($group->customers_count ?? 0),
            'default_price_list_name' => $group->defaultPriceList?->name,
            'default_price_list_code' => $group->defaultPriceList?->code,
            'default_price_list_is_default' => $group->defaultPriceList?->is_default,
        ];
    }
}

