<?php

namespace App\Actions\Tenant\Order;

use App\Models\Order;
use App\Models\User;
use App\Services\Tenant\OrderService;

class CreateOrderAction
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function execute(User $user, array $data): Order
    {
        return $this->orderService->create($user, $data);
    }
}
