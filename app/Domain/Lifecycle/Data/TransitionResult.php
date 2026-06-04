<?php

namespace App\Domain\Lifecycle\Data;

class TransitionResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?string $reason = null,
        public readonly ?string $errorCode = null,
        public readonly array $metadata = [],
    ) {
    }

    public static function accepted(string $fromState, string $toState, array $metadata = []): self
    {
        return new self(
            accepted: true,
            fromState: strtoupper($fromState),
            toState: strtoupper($toState),
            metadata: $metadata,
        );
    }

    public static function rejected(
        string $fromState,
        string $toState,
        string $reason,
        ?string $errorCode = null,
        array $metadata = [],
    ): self {
        return new self(
            accepted: false,
            fromState: strtoupper($fromState),
            toState: strtoupper($toState),
            reason: $reason,
            errorCode: $errorCode,
            metadata: $metadata,
        );
    }

    public function isRejected(): bool
    {
        return ! $this->accepted;
    }
}
