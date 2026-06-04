<?php

namespace App\Actions\Tenant\ReturnOrder;

use App\Models\ReturnOrder;
use App\Models\User;
use App\Services\Tenant\ReturnOrderService;

class CreateReturnOrderAction
{
    public function __construct(private readonly ReturnOrderService $returnOrderService)
    {
    }

    public function execute(User $user, array $data): ReturnOrder
    {
        return $this->returnOrderService->create($user, $data);
    }
}
