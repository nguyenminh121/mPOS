<?php

namespace App\Actions\Tenant\ReturnOrder;

use App\Models\ReturnOrder;
use App\Models\User;
use App\Services\Tenant\ReturnOrderService;

class UpdateReturnOrderAction
{
    public function __construct(private readonly ReturnOrderService $returnOrderService)
    {
    }

    public function execute(User $user, ReturnOrder $returnOrder, array $data): ReturnOrder
    {
        return $this->returnOrderService->update($user, $returnOrder, $data);
    }
}
