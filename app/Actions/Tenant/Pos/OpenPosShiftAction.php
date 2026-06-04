<?php

namespace App\Actions\Tenant\Pos;

use App\Models\PosShift;
use App\Models\User;
use App\Services\Tenant\PosService;

class OpenPosShiftAction
{
    public function __construct(private readonly PosService $posService)
    {
    }

    public function execute(User $user, array $data): PosShift
    {
        return $this->posService->openShift($user, $data);
    }
}
