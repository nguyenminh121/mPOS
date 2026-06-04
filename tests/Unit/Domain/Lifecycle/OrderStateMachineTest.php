<?php

use App\Domain\Lifecycle\Data\TransitionContext;
use App\Domain\Lifecycle\Orders\OrderStateMachine;

test('order state machine accepts same-state transition', function () {
    $machine = new OrderStateMachine();

    $result = $machine->transition('draft', 'draft', TransitionContext::actor(99, 12, 'corr-01'));

    expect($result->accepted)->toBeTrue()
        ->and($result->fromState)->toBe('DRAFT')
        ->and($result->toState)->toBe('DRAFT')
        ->and($result->metadata['actor_id'])->toBe(99)
        ->and($result->metadata['tenant_id'])->toBe(12)
        ->and($result->metadata['correlation_id'])->toBe('corr-01');
});

it('accepts legal order transitions', function (string $from, string $to) {
    $machine = new OrderStateMachine();

    $result = $machine->transition($from, $to, TransitionContext::system());

    expect($result->accepted)->toBeTrue()
        ->and($result->errorCode)->toBeNull();
})->with([
    ['PREORDER', 'DRAFT'],
    ['PREORDER', 'PROCESSING'],
    ['PREORDER', 'CONFIRMED'],
    ['PREORDER', 'COMPLETED'],
    ['PREORDER', 'CANCELLED'],
    ['DRAFT', 'PREORDER'],
    ['DRAFT', 'PROCESSING'],
    ['DRAFT', 'CONFIRMED'],
    ['DRAFT', 'COMPLETED'],
    ['DRAFT', 'CANCELLED'],
    ['PROCESSING', 'CONFIRMED'],
    ['PROCESSING', 'COMPLETED'],
    ['PROCESSING', 'CANCELLED'],
    ['CONFIRMED', 'COMPLETED'],
    ['CONFIRMED', 'CANCELLED'],
    ['COMPLETED', 'CANCELLED'],
]);

it('rejects illegal order transitions', function (string $from, string $to, string $errorCode) {
    $machine = new OrderStateMachine();

    $result = $machine->transition($from, $to, TransitionContext::system());

    expect($result->accepted)->toBeFalse()
        ->and($result->errorCode)->toBe($errorCode)
        ->and($result->reason)->not->toBeNull();
})->with([
    ['CANCELLED', 'DRAFT', 'ORDER_CANCELLED_LOCKED'],
    ['COMPLETED', 'PROCESSING', 'ORDER_ILLEGAL_TRANSITION'],
    ['CONFIRMED', 'DRAFT', 'ORDER_ILLEGAL_TRANSITION'],
    ['PROCESSING', 'PREORDER', 'ORDER_ILLEGAL_TRANSITION'],
    ['INVALID', 'DRAFT', 'ORDER_INVALID_FROM_STATE'],
    ['DRAFT', 'UNKNOWN', 'ORDER_INVALID_TO_STATE'],
]);
