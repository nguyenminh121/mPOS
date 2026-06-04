<?php

namespace App\Domain\Lifecycle\Specification;

use App\Domain\Lifecycle\Contracts\SpecificationInterface;

class SeparationOfDutySpecification implements SpecificationInterface
{
    private ?string $lastMessage = null;

    public function __construct(
        private readonly string $actorKey = 'actor_id',
        private readonly string $creatorKey = 'creator_id',
    ) {
    }

    public function isSatisfiedBy(mixed $subject): bool
    {
        $this->lastMessage = null;

        if (! is_array($subject)) {
            $this->lastMessage = 'SoD input không hợp lệ.';

            return false;
        }

        $actorId = $subject[$this->actorKey] ?? null;
        $creatorId = $subject[$this->creatorKey] ?? null;

        if (! is_numeric($actorId) || ! is_numeric($creatorId)) {
            $this->lastMessage = 'Thiếu actor/creator context cho kiểm tra SoD.';

            return false;
        }

        if ((int) $actorId === (int) $creatorId) {
            $this->lastMessage = 'Người tạo không được tự duyệt/chốt chứng từ (SoD).';

            return false;
        }

        return true;
    }

    public function message(): ?string
    {
        return $this->lastMessage;
    }
}
