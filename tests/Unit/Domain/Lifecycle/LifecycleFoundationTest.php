<?php

use App\Domain\Lifecycle\Contracts\SpecificationInterface;
use App\Domain\Lifecycle\Data\TransitionContext;
use App\Domain\Lifecycle\Data\TransitionResult;
use App\Domain\Lifecycle\Specification\CompositeSpecification;

test('transition context actor factory builds expected values', function () {
    $context = TransitionContext::actor(
        actorId: 101,
        tenantId: 15,
        correlationId: 'corr-001',
        metadata: ['source' => 'test'],
    );

    expect($context->actorId)->toBe(101)
        ->and($context->tenantId)->toBe(15)
        ->and($context->correlationId)->toBe('corr-001')
        ->and($context->metadata)->toBe(['source' => 'test']);
});

test('transition result accepted helper uppercases states', function () {
    $result = TransitionResult::accepted('draft', 'completed', ['flow' => 'order']);

    expect($result->accepted)->toBeTrue()
        ->and($result->fromState)->toBe('DRAFT')
        ->and($result->toState)->toBe('COMPLETED')
        ->and($result->metadata)->toBe(['flow' => 'order']);
});

test('transition result rejected helper stores reason and code', function () {
    $result = TransitionResult::rejected('issued', 'cancelled', 'Not allowed', 'INVOICE_LOCK');

    expect($result->accepted)->toBeFalse()
        ->and($result->isRejected())->toBeTrue()
        ->and($result->reason)->toBe('Not allowed')
        ->and($result->errorCode)->toBe('INVOICE_LOCK')
        ->and($result->fromState)->toBe('ISSUED')
        ->and($result->toState)->toBe('CANCELLED');
});

test('composite specification allOf requires all specs to pass', function () {
    $subject = ['role' => 'staff'];

    $roleSpec = new class implements SpecificationInterface
    {
        public function isSatisfiedBy(mixed $subject): bool
        {
            return is_array($subject) && ($subject['role'] ?? null) === 'manager';
        }

        public function message(): ?string
        {
            return 'Role not allowed';
        }
    };

    $tenantSpec = new class implements SpecificationInterface
    {
        public function isSatisfiedBy(mixed $subject): bool
        {
            return true;
        }

        public function message(): ?string
        {
            return null;
        }
    };

    $composite = CompositeSpecification::allOf($roleSpec, $tenantSpec);

    expect($composite->isSatisfiedBy($subject))->toBeFalse()
        ->and($composite->message())->toBe('Role not allowed');
});

test('composite specification anyOf passes if one spec matches', function () {
    $subject = ['mode' => 'replace'];

    $cancelSpec = new class implements SpecificationInterface
    {
        public function isSatisfiedBy(mixed $subject): bool
        {
            return is_array($subject) && ($subject['mode'] ?? null) === 'cancel';
        }

        public function message(): ?string
        {
            return 'Not cancel mode';
        }
    };

    $replaceSpec = new class implements SpecificationInterface
    {
        public function isSatisfiedBy(mixed $subject): bool
        {
            return is_array($subject) && ($subject['mode'] ?? null) === 'replace';
        }

        public function message(): ?string
        {
            return 'Not replace mode';
        }
    };

    $composite = CompositeSpecification::anyOf($cancelSpec, $replaceSpec);

    expect($composite->isSatisfiedBy($subject))->toBeTrue();
});
