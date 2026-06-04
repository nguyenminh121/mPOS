<?php

namespace App\Actions\Tenant\ImportReceipt;

use App\Models\ImportReceipt;
use App\Models\User;
use App\Services\Tenant\ImportReceiptService;

class UpdateImportReceiptAction
{
    public function __construct(private readonly ImportReceiptService $importReceiptService)
    {
    }

    public function execute(User $user, ImportReceipt $receipt, array $data): ImportReceipt
    {
        return $this->importReceiptService->update($user, $receipt, $data);
    }
}
