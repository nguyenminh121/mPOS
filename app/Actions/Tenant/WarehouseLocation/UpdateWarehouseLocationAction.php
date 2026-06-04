<?php

namespace App\Actions\Tenant\WarehouseLocation;

use App\Models\User;
use App\Models\WarehouseLocation;
use App\Services\Tenant\WarehouseLocationService;

class UpdateWarehouseLocationAction
{
    public function __construct(private readonly WarehouseLocationService $warehouseLocationService)
    {
    }

    public function execute(User $user, WarehouseLocation $warehouseLocation, array $data): WarehouseLocation
    {
        return $this->warehouseLocationService->update($user, $warehouseLocation, $data);
    }
}
