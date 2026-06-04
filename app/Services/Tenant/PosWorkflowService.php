<?php

namespace App\Services\Tenant;

use App\Models\Order;
use App\Models\ReturnOrder;
use App\Models\User;

class PosWorkflowService
{
    public function __construct(
        private readonly PosService $posService,
        private readonly OrderService $orderService,
        private readonly ReturnOrderService $returnOrderService,
    ) {
    }

    public function createRetail(User $user, array $data): Order
    {
        return $this->posService->createSale($user, $data);
    }

    public function createPreorder(User $user, array $data): Order
    {
        $status = strtoupper((string) ($data['status'] ?? 'PREORDER'));
        if (! in_array($status, ['PREORDER', 'DRAFT'], true)) {
            $status = 'PREORDER';
        }

        $payload = [
            ...$data,
            'status' => $status,
            'paid_amount' => (float) ($data['paid_amount'] ?? 0),
            'type' => $data['type'] ?? 'RETAIL',
        ];

        return $this->orderService->create($user, $payload);
    }

    public function createReturn(User $user, array $data): ReturnOrder
    {
        return $this->returnOrderService->create($user, [
            ...$data,
            'status' => 'COMPLETED',
        ]);
    }
}
