<?php

namespace App\Actions\Tenant\Surcharge;

use App\Models\SurchargeCatalog;
use App\Models\User;
use App\Services\Tenant\SurchargeCatalogService;

class CreateSurchargeCatalogAction
{
    public function __construct(private readonly SurchargeCatalogService $surchargeCatalogService)
    {
    }

    public function execute(User $user, array $data): SurchargeCatalog
    {
        return $this->surchargeCatalogService->create($user, $data);
    }
}
