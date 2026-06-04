<?php

namespace App\Actions\Tenant\Uom;

use App\Models\Uom;
use App\Models\User;
use App\Services\Tenant\UomService;

class DeleteUomAction
{
    public function __construct(private readonly UomService $uomService)
    {
    }

    public function execute(User $user, Uom $uom): void
    {
        $this->uomService->delete($user, $uom);
    }
}
