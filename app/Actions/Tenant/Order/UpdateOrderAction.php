<?php

namespace App\Actions\Tenant\Order;

use Illuminate\Container\Container;
use App\Models\Order;
use App\Models\User;
use App\Services\Tenant\OrderDomainService;
use App\Services\Tenant\OrderService;

class UpdateOrderAction
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderDomainService $orderDomainService,
    ) {
    }

    public function execute(User $user, Order $order, array $data): Order
    {
        if ($this->isDomainServiceOrderEnabled()) {
            return $this->orderDomainService->update($user, $order, $data);
        }

        return $this->orderService->update($user, $order, $data);
    }

    private function isDomainServiceOrderEnabled(): bool
    {
        $envValue = getenv('DOMAIN_SERVICE_ORDER_ENABLED');
        if ($envValue !== false) {
            return filter_var((string) $envValue, FILTER_VALIDATE_BOOL);
        }

        $container = Container::getInstance();
        if ($container && $container->bound('config')) {
            return (bool) config('state_machine.domain_services.order', false);
        }

        return filter_var((string) env('DOMAIN_SERVICE_ORDER_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
    }
}
