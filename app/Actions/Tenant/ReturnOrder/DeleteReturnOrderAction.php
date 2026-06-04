<?php

namespace App\Actions\Tenant\ReturnOrder;

use App\Models\ReturnOrder;
use App\Models\User;
use App\Services\Tenant\ReturnOrderService;

class DeleteReturnOrderAction
{
    public function __construct(private readonly ReturnOrderService $returnOrderService)
    {
    }

    public function execute(User $user, ReturnOrder $returnOrder): void
    {
        $this->returnOrderService->delete($user, $returnOrder);
    }
}
