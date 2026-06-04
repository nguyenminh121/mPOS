<?php

namespace App\Domain\Lifecycle\Specification;

use App\Domain\Lifecycle\Contracts\SpecificationInterface;

class TenantOwnershipSpecification implements SpecificationInterface
{
    private ?string $lastMessage = null;

    public function __construct(
        private readonly string $actorTenantKey = 'actor_tenant_id',
        private readonly string $resourceTenantKey = 'resource_tenant_id',
    ) {
    }

    public function isSatisfiedBy(mixed $subject): bool
    {
        $this->lastMessage = null;

        if (! is_array($subject)) {
            $this->lastMessage = 'Tenant ownership input không hợp lệ.';

            return false;
        }

        $actorTenantId = $subject[$this->actorTenantKey] ?? null;
        $resourceTenantId = $subject[$this->resourceTenantKey] ?? null;

        if (! is_numeric($actorTenantId) || ! is_numeric($resourceTenantId)) {
            $this->lastMessage = 'Thiếu tenant context để xác thực quyền truy cập.';

            return false;
        }

        if ((int) $actorTenantId !== (int) $resourceTenantId) {
            $this->lastMessage = 'Dữ liệu không thuộc tenant hiện tại.';

            return false;
        }

        return true;
    }

    public function message(): ?string
    {
        return $this->lastMessage;
    }
}
