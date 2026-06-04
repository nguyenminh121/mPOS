<?php

namespace App\Actions\Tenant\ImportReceipt;

use App\Models\ImportReceipt;
use App\Models\User;
use App\Services\Tenant\ImportReceiptService;

class CreateImportReceiptAction
{
    public function __construct(private readonly ImportReceiptService $importReceiptService)
    {
    }

    public function execute(User $user, array $data): ImportReceipt
    {
        return $this->importReceiptService->create($user, $data);
    }
}
