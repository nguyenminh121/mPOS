<?php

namespace App\Actions\Tenant\Invoice;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Tenant\InvoiceService;

class DeleteInvoiceAction
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function execute(User $user, Invoice $invoice): void
    {
        $this->invoiceService->delete($user, $invoice);
    }
}
