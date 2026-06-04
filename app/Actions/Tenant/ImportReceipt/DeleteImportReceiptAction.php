<?php

namespace App\Actions\Tenant\ImportReceipt;

use App\Models\ImportReceipt;
use App\Models\User;
use App\Services\Tenant\ImportReceiptService;

class DeleteImportReceiptAction
{
    public function __construct(private readonly ImportReceiptService $importReceiptService)
    {
    }

    public function execute(User $user, ImportReceipt $receipt): void
    {
        $this->importReceiptService->delete($user, $receipt);
    }
}
