<?php

namespace App\Actions\Tenant\Pos;

use App\Models\Order;
use App\Models\User;
use App\Services\Tenant\PosWorkflowService;

class CreatePreorderSaleAction
{
    public function __construct(private readonly PosWorkflowService $posWorkflowService)
    {
    }

    public function execute(User $user, array $data): Order
    {
        return $this->posWorkflowService->createPreorder($user, $data);
    }
}
