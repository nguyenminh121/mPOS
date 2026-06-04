<?php

namespace App\Actions\Tenant\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Services\Tenant\PaymentService;

class CreatePaymentAction
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function execute(User $user, array $data): Payment
    {
        return $this->paymentService->recordOrderDebtPayment($user, $data);
    }
}
