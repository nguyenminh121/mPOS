<?php

namespace App\Domain\Lifecycle\Data;

class TransitionContext
{
    public function __construct(
        public readonly ?int $actorId,
        public readonly ?int $tenantId,
        public readonly ?string $correlationId = null,
        public readonly array $metadata = [],
    ) {
    }

    public static function system(?int $tenantId = null, ?string $correlationId = null, array $metadata = []): self
    {
        return new self(
            actorId: null,
            tenantId: $tenantId,
            correlationId: $correlationId,
            metadata: $metadata,
        );
    }

    public static function actor(int $actorId, ?int $tenantId = null, ?string $correlationId = null, array $metadata = []): self
    {
        return new self(
            actorId: $actorId,
            tenantId: $tenantId,
            correlationId: $correlationId,
            metadata: $metadata,
        );
    }
}
