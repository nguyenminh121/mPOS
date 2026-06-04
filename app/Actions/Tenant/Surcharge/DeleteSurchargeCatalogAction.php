<?php

namespace App\Actions\Tenant\Surcharge;

use App\Models\SurchargeCatalog;
use App\Models\User;
use App\Services\Tenant\SurchargeCatalogService;

class DeleteSurchargeCatalogAction
{
    public function __construct(private readonly SurchargeCatalogService $surchargeCatalogService)
    {
    }

    public function execute(User $user, SurchargeCatalog $surchargeCatalog): void
    {
        $this->surchargeCatalogService->delete($user, $surchargeCatalog);
    }
}
