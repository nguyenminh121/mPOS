<?php

namespace App\Actions\Tenant\Uom;

use App\Models\Uom;
use App\Models\User;
use App\Services\Tenant\UomService;

class UpdateUomAction
{
    public function __construct(private readonly UomService $uomService)
    {
    }

    public function execute(User $user, Uom $uom, array $data): Uom
    {
        return $this->uomService->update($user, $uom, $data);
    }
}
