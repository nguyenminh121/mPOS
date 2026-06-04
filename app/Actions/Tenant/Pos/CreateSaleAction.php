<?php

namespace App\Actions\Tenant\Pos;

use App\Models\Order;
use App\Models\User;
use App\Services\Tenant\PosService;

class CreateSaleAction
{
    public function __construct(private readonly PosService $posService)
    {
    }

    public function execute(User $user, array $data): Order
    {
        return $this->posService->createSale($user, $data);
    }
}
