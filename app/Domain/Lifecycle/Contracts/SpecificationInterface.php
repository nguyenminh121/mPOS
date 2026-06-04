<?php

namespace App\Domain\Lifecycle\Contracts;

interface SpecificationInterface
{
    public function isSatisfiedBy(mixed $subject): bool;

    public function message(): ?string;
}
