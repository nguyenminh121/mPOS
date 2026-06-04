<?php

namespace App\Actions\Tenant\PaymentMethodConfig;

use App\Models\PaymentMethodConfig;
use App\Models\User;
use App\Services\Tenant\PaymentMethodConfigService;

class UpdatePaymentMethodConfigAction
{
    public function __construct(private readonly PaymentMethodConfigService $paymentMethodConfigService)
    {
    }

    public function execute(User $user, PaymentMethodConfig $paymentMethodConfig, array $data): PaymentMethodConfig
    {
        return $this->paymentMethodConfigService->update($user, $paymentMethodConfig, $data);
    }
}
