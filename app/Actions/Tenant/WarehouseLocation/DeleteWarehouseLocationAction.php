<?php

namespace App\Actions\Tenant\WarehouseLocation;

use App\Models\User;
use App\Models\WarehouseLocation;
use App\Services\Tenant\WarehouseLocationService;

class DeleteWarehouseLocationAction
{
    public function __construct(private readonly WarehouseLocationService $warehouseLocationService)
    {
    }

    public function execute(User $user, WarehouseLocation $warehouseLocation): void
    {
        $this->warehouseLocationService->delete($user, $warehouseLocation);
    }
}
