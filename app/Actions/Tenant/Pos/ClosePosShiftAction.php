<?php

namespace App\Actions\Tenant\Pos;

use App\Models\PosShift;
use App\Models\User;
use App\Services\Tenant\PosService;

class ClosePosShiftAction
{
    public function __construct(private readonly PosService $posService)
    {
    }

    public function execute(User $user, PosShift $shift, array $data): PosShift
    {
        return $this->posService->closeShift($user, $shift, $data);
    }
}
