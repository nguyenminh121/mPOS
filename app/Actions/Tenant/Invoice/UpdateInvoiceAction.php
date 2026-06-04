<?php

namespace App\Actions\Tenant\Invoice;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Tenant\InvoiceService;

class UpdateInvoiceAction
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function execute(User $user, Invoice $invoice, array $data): Invoice
    {
        return $this->invoiceService->update($user, $invoice, $data);
    }
}
