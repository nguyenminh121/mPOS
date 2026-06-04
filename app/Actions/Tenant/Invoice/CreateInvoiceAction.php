<?php

namespace App\Actions\Tenant\Invoice;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Tenant\InvoiceService;

class CreateInvoiceAction
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function execute(User $user, array $data): Invoice
    {
        return $this->invoiceService->create($user, $data);
    }
}
