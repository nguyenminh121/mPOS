<?php

namespace App\Actions\Tenant\PaymentMethodConfig;

use App\Models\PaymentMethodConfig;
use App\Models\User;
use App\Services\Tenant\PaymentMethodConfigService;

class CreatePaymentMethodConfigAction
{
    public function __construct(private readonly PaymentMethodConfigService $paymentMethodConfigService)
    {
    }

    public function execute(User $user, array $data): PaymentMethodConfig
    {
        return $this->paymentMethodConfigService->create($user, $data);
    }
}
