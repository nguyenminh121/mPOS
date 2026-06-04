<?php

use App\Actions\Tenant\Order\UpdateOrderAction;
use App\Models\Order;
use App\Models\User;
use App\Services\Tenant\OrderDomainService;
use App\Services\Tenant\OrderService;

afterEach(function () {
    putenv('DOMAIN_SERVICE_ORDER_ENABLED');
});

test('update order action uses legacy order service when domain service flag is disabled', function () {
    putenv('DOMAIN_SERVICE_ORDER_ENABLED=false');

    $user = new User(['id' => 11, 'tenant_id' => 7]);
    $order = new Order(['id' => 101, 'tenant_id' => 7, 'status' => 'DRAFT']);
    $payload = ['status' => 'PROCESSING'];

    $legacyService = \Mockery::mock(OrderService::class);
    $domainService = \Mockery::mock(OrderDomainService::class);

    $legacyService
        ->shouldReceive('update')
        ->once()
        ->with($user, $order, $payload)
        ->andReturn($order);

    $domainService
        ->shouldReceive('update')
        ->never();

    $action = new UpdateOrderAction($legacyService, $domainService);

    $result = $action->execute($user, $order, $payload);

    expect((int) $result->id)->toBe((int) $order->id);
});

test('update order action uses domain service when domain service flag is enabled', function () {
    putenv('DOMAIN_SERVICE_ORDER_ENABLED=true');

    $user = new User(['id' => 12, 'tenant_id' => 8]);
    $order = new Order(['id' => 102, 'tenant_id' => 8, 'status' => 'DRAFT']);
    $payload = ['status' => 'CONFIRMED'];

    $legacyService = \Mockery::mock(OrderService::class);
    $domainService = \Mockery::mock(OrderDomainService::class);

    $legacyService
        ->shouldReceive('update')
        ->never();

    $domainService
        ->shouldReceive('update')
        ->once()
        ->with($user, $order, $payload)
        ->andReturn($order);

    $action = new UpdateOrderAction($legacyService, $domainService);

    $result = $action->execute($user, $order, $payload);

    expect($result)->toBe($order);
});
