<?php

namespace App\Actions\Tenant\Warehouse;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\WarehouseService;

class CreateWarehouseAction
{
    public function __construct(private readonly WarehouseService $warehouseService)
    {
    }

    public function execute(User $user, array $data): Warehouse
    {
        return $this->warehouseService->create($user, $data);
    }
}
