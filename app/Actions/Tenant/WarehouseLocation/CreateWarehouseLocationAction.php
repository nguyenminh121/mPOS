<?php

namespace App\Actions\Tenant\WarehouseLocation;

use App\Models\User;
use App\Models\WarehouseLocation;
use App\Services\Tenant\WarehouseLocationService;

class CreateWarehouseLocationAction
{
    public function __construct(private readonly WarehouseLocationService $warehouseLocationService)
    {
    }

    public function execute(User $user, array $data): WarehouseLocation
    {
        return $this->warehouseLocationService->create($user, $data);
    }
}
