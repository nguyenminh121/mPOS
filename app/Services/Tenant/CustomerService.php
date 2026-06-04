<?php

namespace App\Services\Tenant;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\ReturnOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly CustomerDebtService $customerDebtService,
    ) {
    }

    public function list(User $user, array $filters = []): Collection
    {
        $groupId = Arr::get($filters, 'group_id');
        $type = trim((string) Arr::get($filters, 'type', ''));
        $address = trim((string) Arr::get($filters, 'address', ''));

        $customers = Customer::query()
            ->where('tenant_id', $user->tenant_id)
            ->when(is_numeric($groupId) && (int) $groupId > 0, fn ($query) => $query->where('group_id', (int) $groupId))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($address !== '', function ($query) use ($address) {
                $query->where(function ($nested) use ($address) {
                    $nested->where('address', 'like', '%'.$address.'%')
                        ->orWhereHas('addresses', fn ($addressQuery) => $addressQuery->where('address', 'like', '%'.$address.'%'));
                });
            })
            ->with([
                'group:id,name,code',
                'priceList:id,name,code',
                'defaultAddress:id,customer_id,address,receiver_name,receiver_phone,default_address',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'email',
                'phone',
                'address',
                'type',
                'tax_code',
                'company_name',
                'debt_amount',
                'total_spent',
                'credit_limit',
                'default_due_days',
                'description',
                'group_id',
                'price_list_id',
                'is_active',
            ]);

        $tenantId = (int) $user->tenant_id;

        $debtByCustomer = $this->customerDebtService->mapOutstandingByCustomer($tenantId);
        $orderSalesByCustomer = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(final_amount) as total_sold')
            ->pluck('total_sold', 'customer_id')
            ->map(fn ($value): float => max(0, round((float) $value, 2)));

        $returnSalesByCustomer = ReturnOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(total_amount) as total_return')
            ->pluck('total_return', 'customer_id')
            ->map(fn ($value): float => max(0, round((float) $value, 2)));

        return $customers->map(function (Customer $customer) use ($debtByCustomer, $orderSalesByCustomer, $returnSalesByCustomer): Customer {
            $customerId = (int) $customer->id;
            $debt = (float) ($debtByCustomer->get($customerId, 0));
            $totalSold = (float) ($orderSalesByCustomer->get($customerId, 0));
            $totalReturn = (float) ($returnSalesByCustomer->get($customerId, 0));

            $customer->debt_amount = $debt;
            $customer->setAttribute('current_debt', $debt);
            $customer->setAttribute('total_sold', $totalSold);
            $customer->setAttribute('total_return', $totalReturn);
            $customer->setAttribute('total_sold_minus_return', max(0, round($totalSold - $totalReturn, 2)));
            $customer->setAttribute('display_address', (string) ($customer->defaultAddress?->address ?? $customer->address ?? ''));

            return $customer;
        });
    }

    public function create(User $user, array $data): Customer
    {
        $this->assertGroupBelongsToTenant($user, Arr::get($data, 'group_id'));
        $this->assertPriceListBelongsToTenant($user, Arr::get($data, 'price_list_id'));

        $payload = Arr::only($data, [
            'code',
            'name',
            'email',
            'phone',
            'address',
            'type',
            'tax_code',
            'company_name',
            'credit_limit',
            'default_due_days',
            'description',
            'group_id',
            'price_list_id',
            'is_active',
        ]);

        return DB::transaction(function () use ($user, $payload): Customer {
            $payload['code'] = $this->ensureCustomerCode($user, Arr::get($payload, 'code'));

            return Customer::query()->create([
                ...$payload,
                'tenant_id' => $user->tenant_id,
                'created_by' => $user->id,
            ]);
        });
    }

    public function update(User $user, Customer $customer, array $data): Customer
    {
        $this->guardTenant($user, $customer);

        if (array_key_exists('group_id', $data)) {
            $this->assertGroupBelongsToTenant($user, Arr::get($data, 'group_id'));
        }

        if (array_key_exists('price_list_id', $data)) {
            $this->assertPriceListBelongsToTenant($user, Arr::get($data, 'price_list_id'));
        }

        return DB::transaction(function () use ($user, $customer, $data): Customer {
            $payload = Arr::only($data, [
                'code',
                'name',
                'email',
                'phone',
                'address',
                'type',
                'tax_code',
                'company_name',
                'credit_limit',
                'default_due_days',
                'description',
                'group_id',
                'price_list_id',
                'is_active',
            ]);

            if (array_key_exists('code', $payload)) {
                $payload['code'] = $this->ensureCustomerCode($user, $payload['code'], (int) $customer->id);
            }

            $customer->fill($payload);
            $customer->save();

            return $customer->refresh();
        });
    }

    public function delete(User $user, Customer $customer): void
    {
        $this->guardTenant($user, $customer);

        $customer->delete();
    }

    private function assertGroupBelongsToTenant(User $user, mixed $groupId): void
    {
        if ($groupId === null) {
            return;
        }

        $exists = CustomerGroup::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey((int) $groupId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'group_id' => 'Nhóm khách hàng không tồn tại trong tenant.',
            ]);
        }
    }

    private function assertPriceListBelongsToTenant(User $user, mixed $priceListId): void
    {
        if ($priceListId === null) {
            return;
        }

        $exists = PriceList::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey((int) $priceListId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'price_list_id' => 'Bảng giá không tồn tại trong tenant.',
            ]);
        }
    }

    private function guardTenant(User $user, Customer $customer): void
    {
        if ((int) $customer->tenant_id !== (int) $user->tenant_id) {
            throw ValidationException::withMessages([
                'customer' => 'Customer does not belong to this tenant.',
            ]);
        }
    }

    private function ensureCustomerCode(User $user, mixed $requestedCode, ?int $ignoreCustomerId = null): string
    {
        $normalized = $this->normalizeCustomerCode($requestedCode);
        if ($normalized !== null) {
            return $normalized;
        }

        return $this->generateNextCustomerCode($user, $ignoreCustomerId);
    }

    private function normalizeCustomerCode(mixed $code): ?string
    {
        $value = trim((string) ($code ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_strtoupper($value);
    }

    private function generateNextCustomerCode(User $user, ?int $ignoreCustomerId = null): string
    {
        $codes = Customer::query()
            ->withTrashed()
            ->where('tenant_id', $user->tenant_id)
            ->when($ignoreCustomerId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreCustomerId))
            ->where('code', 'like', 'KH%')
            ->lockForUpdate()
            ->pluck('code');

        $maxSequence = 0;
        foreach ($codes as $code) {
            if (! is_string($code)) {
                continue;
            }

            if (preg_match('/^KH(\d+)$/i', trim($code), $matches) !== 1) {
                continue;
            }

            $maxSequence = max($maxSequence, (int) $matches[1]);
        }

        return 'KH' . str_pad((string) ($maxSequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
