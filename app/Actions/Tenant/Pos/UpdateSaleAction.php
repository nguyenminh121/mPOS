<?php

namespace App\Actions\Tenant\Pos;

use App\Models\Order;
use App\Models\User;
use App\Services\Tenant\OrderService;

class UpdateSaleAction
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function execute(User $user, Order $sale, array $data): Order
    {
        return $this->orderService->update($user, $sale, $data);
    }
}
