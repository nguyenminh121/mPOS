<?php

namespace App\Domain\Lifecycle\Contracts;

use App\Domain\Lifecycle\Data\TransitionContext;
use App\Domain\Lifecycle\Data\TransitionResult;

interface StateMachineInterface
{
    public function canTransition(string $fromState, string $toState, TransitionContext $context): bool;

    public function transition(string $fromState, string $toState, TransitionContext $context): TransitionResult;
}
