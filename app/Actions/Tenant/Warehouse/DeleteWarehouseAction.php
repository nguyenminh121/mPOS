<?php

namespace App\Actions\Tenant\Warehouse;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\WarehouseService;

class DeleteWarehouseAction
{
    public function __construct(private readonly WarehouseService $warehouseService)
    {
    }

    public function execute(User $user, Warehouse $warehouse): void
    {
        $this->warehouseService->delete($user, $warehouse);
    }
}
