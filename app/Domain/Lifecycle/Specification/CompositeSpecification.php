<?php

namespace App\Domain\Lifecycle\Specification;

use App\Domain\Lifecycle\Contracts\SpecificationInterface;

class CompositeSpecification implements SpecificationInterface
{
    /**
     * @var array<int, SpecificationInterface>
     */
    private array $specifications;

    private ?string $lastMessage = null;

    public function __construct(array $specifications, private readonly bool $requireAll = true)
    {
        $this->specifications = array_values(array_filter(
            $specifications,
            static fn ($specification) => $specification instanceof SpecificationInterface,
        ));
    }

    public static function allOf(SpecificationInterface ...$specifications): self
    {
        return new self($specifications, true);
    }

    public static function anyOf(SpecificationInterface ...$specifications): self
    {
        return new self($specifications, false);
    }

    public function isSatisfiedBy(mixed $subject): bool
    {
        $this->lastMessage = null;

        if ($this->specifications === []) {
            return true;
        }

        if ($this->requireAll) {
            foreach ($this->specifications as $specification) {
                if (! $specification->isSatisfiedBy($subject)) {
                    $this->lastMessage = $specification->message();

                    return false;
                }
            }

            return true;
        }

        foreach ($this->specifications as $specification) {
            if ($specification->isSatisfiedBy($subject)) {
                return true;
            }

            $this->lastMessage = $specification->message();
        }

        return false;
    }

    public function message(): ?string
    {
        return $this->lastMessage;
    }
}
