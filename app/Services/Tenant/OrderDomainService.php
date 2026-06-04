<?php

namespace App\Services\Tenant;

use App\Domain\Lifecycle\Exceptions\DomainRuleViolationException;
use App\Domain\Lifecycle\Data\TransitionContext;
use App\Domain\Lifecycle\Orders\OrderStateMachine;
use App\Domain\Lifecycle\Specification\TenantOwnershipSpecification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderDomainService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderStateMachine $orderStateMachine,
        private readonly TenantOwnershipSpecification $tenantOwnershipSpecification,
    ) {
    }

    public function update(User $user, Order $order, array $data): Order
    {
        $this->assertTenantOwnership($user, $order);
        $this->auditTransitionDecision($user, $order, $data);

        return $this->orderService->update($user, $order, $data);
    }

    private function assertTenantOwnership(User $user, Order $order): void
    {
        $allowed = $this->tenantOwnershipSpecification->isSatisfiedBy([
            'actor_tenant_id' => (int) $user->tenant_id,
            'resource_tenant_id' => (int) $order->tenant_id,
        ]);

        if (! $allowed) {
            $message = $this->tenantOwnershipSpecification->message() ?? 'Order does not belong to this tenant.';

            $container = Container::getInstance();
            if ($container && $container->bound('validator')) {
                throw ValidationException::withMessages([
                    'order' => $message,
                ]);
            }

            throw new DomainRuleViolationException($message, [
                'aggregate' => 'order',
                'rule' => 'tenant_ownership',
            ]);
        }
    }

    private function auditTransitionDecision(User $user, Order $order, array $data): void
    {
        if (! $this->isStateMachineAuditEnabled()) {
            return;
        }

        $from = strtoupper((string) $order->status);
        $to = strtoupper((string) ($data['status'] ?? $order->status));

        $decision = $this->orderStateMachine->transition(
            fromState: $from,
            toState: $to,
            context: TransitionContext::actor(
                actorId: (int) $user->id,
                tenantId: (int) $user->tenant_id,
                correlationId: (string) Str::uuid(),
                metadata: [
                    'aggregate' => 'order',
                    'source' => self::class,
                    'mode' => 'domain_service_pilot',
                ],
            ),
        );

        if (! $this->canWriteAuditLog()) {
            return;
        }

        Log::channel($this->stateMachineAuditChannel())->info('OrderDomainService transition decision', [
            'from' => $from,
            'to' => $to,
            'accepted' => $decision->accepted,
            'reason' => $decision->reason,
            'error_code' => $decision->errorCode,
            'tenant_id' => (int) $user->tenant_id,
            'actor_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'order_code' => (string) $order->code,
        ]);
    }

    private function isStateMachineAuditEnabled(): bool
    {
        $envValue = getenv('STATE_MACHINE_AUDIT_ENABLED');
        if ($envValue !== false) {
            return filter_var((string) $envValue, FILTER_VALIDATE_BOOL);
        }

        $container = Container::getInstance();
        if ($container && $container->bound('config')) {
            return (bool) config('state_machine.audit.enabled', true);
        }

        return filter_var((string) env('STATE_MACHINE_AUDIT_ENABLED', 'true'), FILTER_VALIDATE_BOOL);
    }

    private function stateMachineAuditChannel(): string
    {
        $envValue = getenv('STATE_MACHINE_AUDIT_CHANNEL');
        if (is_string($envValue) && trim($envValue) !== '') {
            return $envValue;
        }

        $container = Container::getInstance();
        if ($container && $container->bound('config')) {
            return (string) config('state_machine.audit.channel', (string) config('logging.default', 'stack'));
        }

        return (string) env('STATE_MACHINE_AUDIT_CHANNEL', 'stack');
    }

    private function canWriteAuditLog(): bool
    {
        $container = Container::getInstance();

        return (bool) ($container && $container->bound('log'));
    }
}
