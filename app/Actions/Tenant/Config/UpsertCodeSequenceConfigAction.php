<?php

namespace App\Actions\Tenant\Config;

use App\Models\CodeSequenceConfig;
use App\Models\User;
use App\Services\Tenant\CodeSequenceService;

class UpsertCodeSequenceConfigAction
{
    public function __construct(private readonly CodeSequenceService $codeSequenceService)
    {
    }

    public function create(User $user, array $data): CodeSequenceConfig
    {
        return $this->codeSequenceService->upsert($user, $data);
    }

    public function update(User $user, CodeSequenceConfig $config, array $data): CodeSequenceConfig
    {
        return $this->codeSequenceService->update($user, $config, $data);
    }
}
