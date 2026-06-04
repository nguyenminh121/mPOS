<?php

namespace App\Actions\Tenant\Payment;

use App\Models\User;
use App\Services\Tenant\PaymentService;

class CreateBatchDebtPaymentAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function execute(User $user, array $data): array
    {
        return $this->paymentService->recordCustomerDebtPaymentFIFO($user, $data);
    }
}
