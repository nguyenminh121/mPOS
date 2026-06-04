<?php

namespace App\Actions\Tenant\Pos;

use App\Models\ReturnOrder;
use App\Models\User;
use App\Services\Tenant\PosWorkflowService;

class CreateReturnSaleAction
{
    public function __construct(private readonly PosWorkflowService $posWorkflowService)
    {
    }

    public function execute(User $user, array $data): ReturnOrder
    {
        return $this->posWorkflowService->createReturn($user, $data);
    }
}
