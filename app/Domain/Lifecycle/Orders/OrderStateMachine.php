<?php

namespace App\Domain\Lifecycle\Orders;

use App\Domain\Lifecycle\Contracts\StateMachineInterface;
use App\Domain\Lifecycle\Data\TransitionContext;
use App\Domain\Lifecycle\Data\TransitionResult;

class OrderStateMachine implements StateMachineInterface
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $allowedTransitions = [
        OrderStates::PREORDER => [
            OrderStates::DRAFT,
            OrderStates::PROCESSING,
            OrderStates::CONFIRMED,
            OrderStates::COMPLETED,
            OrderStates::CANCELLED,
        ],
        OrderStates::DRAFT => [
            OrderStates::PREORDER,
            OrderStates::PROCESSING,
            OrderStates::CONFIRMED,
            OrderStates::COMPLETED,
            OrderStates::CANCELLED,
        ],
        OrderStates::PROCESSING => [
            OrderStates::CONFIRMED,
            OrderStates::COMPLETED,
            OrderStates::CANCELLED,
        ],
        OrderStates::CONFIRMED => [
            OrderStates::COMPLETED,
            OrderStates::CANCELLED,
        ],
        OrderStates::COMPLETED => [
            OrderStates::CANCELLED,
        ],
    ];

    public function canTransition(string $fromState, string $toState, TransitionContext $context): bool
    {
        return $this->transition($fromState, $toState, $context)->accepted;
    }

    public function transition(string $fromState, string $toState, TransitionContext $context): TransitionResult
    {
        $from = OrderStates::normalize($fromState);
        $to = OrderStates::normalize($toState);

        if (! in_array($from, OrderStates::all(), true)) {
            return TransitionResult::rejected(
                fromState: $from,
                toState: $to,
                reason: 'Trạng thái nguồn không hợp lệ.',
                errorCode: 'ORDER_INVALID_FROM_STATE',
            );
        }

        if (! in_array($to, OrderStates::all(), true)) {
            return TransitionResult::rejected(
                fromState: $from,
                toState: $to,
                reason: 'Trạng thái đích không hợp lệ.',
                errorCode: 'ORDER_INVALID_TO_STATE',
            );
        }

        if ($from === $to) {
            return TransitionResult::accepted(
                fromState: $from,
                toState: $to,
                metadata: $this->buildMetadata($context),
            );
        }

        if ($from === OrderStates::CANCELLED) {
            return TransitionResult::rejected(
                fromState: $from,
                toState: $to,
                reason: 'Đơn đã hủy không thể thay đổi trạng thái lại.',
                errorCode: 'ORDER_CANCELLED_LOCKED',
            );
        }

        $nextStates = $this->allowedTransitions[$from] ?? [];
        if (! in_array($to, $nextStates, true)) {
            return TransitionResult::rejected(
                fromState: $from,
                toState: $to,
                reason: 'Luồng trạng thái không hợp lệ. Chỉ cho phép: PREORDER/DRAFT -> PROCESSING -> CONFIRMED -> COMPLETED hoặc chuyển CANCELLED.',
                errorCode: 'ORDER_ILLEGAL_TRANSITION',
            );
        }

        return TransitionResult::accepted(
            fromState: $from,
            toState: $to,
            metadata: $this->buildMetadata($context),
        );
    }

    private function buildMetadata(TransitionContext $context): array
    {
        return [
            'actor_id' => $context->actorId,
            'tenant_id' => $context->tenantId,
            'correlation_id' => $context->correlationId,
            ...$context->metadata,
        ];
    }
}
