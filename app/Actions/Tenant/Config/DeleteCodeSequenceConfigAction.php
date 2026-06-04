<?php

namespace App\Actions\Tenant\Config;

use App\Models\CodeSequenceConfig;
use App\Models\User;
use App\Services\Tenant\CodeSequenceService;

class DeleteCodeSequenceConfigAction
{
    public function __construct(private readonly CodeSequenceService $codeSequenceService)
    {
    }

    public function execute(User $user, CodeSequenceConfig $config): void
    {
        $this->codeSequenceService->delete($user, $config);
    }
}
