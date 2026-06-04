<?php

namespace App\Domain\Lifecycle\Specification;

use App\Domain\Lifecycle\Contracts\SpecificationInterface;

class RoleSpecification implements SpecificationInterface
{
    private ?string $lastMessage = null;

    /**
     * @param array<int, string> $requiredRoles
     */
    public function __construct(
        private readonly array $requiredRoles,
        private readonly bool $requireAll = false,
        private readonly string $rolesKey = 'roles',
    ) {
    }

    public function isSatisfiedBy(mixed $subject): bool
    {
        $this->lastMessage = null;

        if (! is_array($subject)) {
            $this->lastMessage = 'Role specification input không hợp lệ.';

            return false;
        }

        $actualRoles = $subject[$this->rolesKey] ?? [];
        if (! is_array($actualRoles)) {
            $this->lastMessage = 'Danh sách vai trò không hợp lệ.';

            return false;
        }

        $actual = collect($actualRoles)
            ->filter(fn ($role) => is_string($role) && trim($role) !== '')
            ->map(fn (string $role) => strtolower(trim($role)))
            ->values()
            ->all();

        $required = collect($this->requiredRoles)
            ->map(fn (string $role) => strtolower(trim($role)))
            ->values()
            ->all();

        if ($required === []) {
            return true;
        }

        if ($this->requireAll) {
            foreach ($required as $role) {
                if (! in_array($role, $actual, true)) {
                    $this->lastMessage = 'Thiếu vai trò yêu cầu: '.$role;

                    return false;
                }
            }

            return true;
        }

        foreach ($required as $role) {
            if (in_array($role, $actual, true)) {
                return true;
            }
        }

        $this->lastMessage = 'Không có vai trò phù hợp để thực hiện thao tác.';

        return false;
    }

    public function message(): ?string
    {
        return $this->lastMessage;
    }
}
