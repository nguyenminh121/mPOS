<?php

namespace App\Actions\Tenant\Warehouse;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\WarehouseService;

class UpdateWarehouseAction
{
    public function __construct(private readonly WarehouseService $warehouseService)
    {
    }

    public function execute(User $user, Warehouse $warehouse, array $data): Warehouse
    {
        return $this->warehouseService->update($user, $warehouse, $data);
    }
}
