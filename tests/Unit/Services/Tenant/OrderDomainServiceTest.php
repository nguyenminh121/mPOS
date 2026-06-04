<?php

use App\Domain\Lifecycle\Data\TransitionResult;
use App\Domain\Lifecycle\Exceptions\DomainRuleViolationException;
use App\Domain\Lifecycle\Orders\OrderStateMachine;
use App\Domain\Lifecycle\Specification\TenantOwnershipSpecification;
use App\Models\Order;
use App\Models\User;
use App\Services\Tenant\OrderDomainService;
use App\Services\Tenant\OrderService;

afterEach(function () {
    putenv('STATE_MACHINE_AUDIT_ENABLED');
    putenv('STATE_MACHINE_AUDIT_CHANNEL');
});

test('order domain service delegates update when tenant ownership is valid', function () {
    putenv('STATE_MACHINE_AUDIT_ENABLED=false');

    $user = new User(['id' => 10, 'tenant_id' => 3]);
    $order = new Order(['id' => 1001, 'code' => 'ORD-1001', 'tenant_id' => 3, 'status' => 'DRAFT']);
    $payload = ['status' => 'PROCESSING'];

    $orderService = \Mockery::mock(OrderService::class);
    $stateMachine = \Mockery::mock(OrderStateMachine::class);
    $tenantSpec = new TenantOwnershipSpecification();

    $stateMachine
        ->shouldNotReceive('transition');

    $orderService
        ->shouldReceive('update')
        ->once()
        ->with($user, $order, $payload)
        ->andReturn($order);

    $service = new OrderDomainService($orderService, $stateMachine, $tenantSpec);

    $result = $service->update($user, $order, $payload);

    expect($result)->toBe($order);
});

test('order domain service throws validation exception when tenant ownership is invalid', function () {
    putenv('STATE_MACHINE_AUDIT_ENABLED=false');

    $user = new User(['id' => 10, 'tenant_id' => 3]);
    $order = new Order(['id' => 1002, 'code' => 'ORD-1002', 'tenant_id' => 9, 'status' => 'DRAFT']);

    $orderService = \Mockery::mock(OrderService::class);
    $stateMachine = \Mockery::mock(OrderStateMachine::class);
    $tenantSpec = new TenantOwnershipSpecification();

    $orderService
        ->shouldNotReceive('update');

    $stateMachine
        ->shouldNotReceive('transition');

    $service = new OrderDomainService($orderService, $stateMachine, $tenantSpec);

    expect(fn () => $service->update($user, $order, ['status' => 'CONFIRMED']))
        ->toThrow(DomainRuleViolationException::class);
});

test('order domain service audits transition decision when audit is enabled', function () {
    putenv('STATE_MACHINE_AUDIT_ENABLED=true');
    putenv('STATE_MACHINE_AUDIT_CHANNEL=stack');

    $user = new User(['id' => 12, 'tenant_id' => 5]);
    $order = new Order(['id' => 1003, 'code' => 'ORD-1003', 'tenant_id' => 5, 'status' => 'DRAFT']);
    $payload = ['status' => 'CONFIRMED'];

    $orderService = \Mockery::mock(OrderService::class);
    $stateMachine = \Mockery::mock(OrderStateMachine::class);
    $tenantSpec = new TenantOwnershipSpecification();

    $stateMachine
        ->shouldReceive('transition')
        ->once()
        ->andReturn(TransitionResult::accepted('DRAFT', 'CONFIRMED'));

    $orderService
        ->shouldReceive('update')
        ->once()
        ->with($user, $order, $payload)
        ->andReturn($order);

    $service = new OrderDomainService($orderService, $stateMachine, $tenantSpec);

    $result = $service->update($user, $order, $payload);

    expect($result)->toBe($order);
});
