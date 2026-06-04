<?php

namespace App\Actions\Tenant\Surcharge;

use App\Models\SurchargeCatalog;
use App\Models\User;
use App\Services\Tenant\SurchargeCatalogService;

class UpdateSurchargeCatalogAction
{
    public function __construct(private readonly SurchargeCatalogService $surchargeCatalogService)
    {
    }

    public function execute(User $user, SurchargeCatalog $surchargeCatalog, array $data): SurchargeCatalog
    {
        return $this->surchargeCatalogService->update($user, $surchargeCatalog, $data);
    }
}
