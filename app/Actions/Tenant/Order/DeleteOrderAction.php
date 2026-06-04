<?php

namespace App\Actions\Tenant\Order;

use App\Models\Order;
use App\Models\User;
use App\Services\Tenant\OrderService;

class DeleteOrderAction
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function execute(User $user, Order $order): void
    {
        $this->orderService->delete($user, $order);
    }
}
