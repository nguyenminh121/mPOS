<?php

namespace App\Actions\Tenant\Uom;

use App\Models\Uom;
use App\Models\User;
use App\Services\Tenant\UomService;

class CreateUomAction
{
    public function __construct(private readonly UomService $uomService)
    {
    }

    public function execute(User $user, array $data): Uom
    {
        return $this->uomService->create($user, $data);
    }
}
