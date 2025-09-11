<?php

declare(strict_types=1);

namespace App\Application\Response;

final class GetActivePlansQueryResponse
{
    public function __construct(
        public readonly array $plans,
    ) {
    }

    public function isEmpty(): bool
    {
        return empty($this->plans);
    }

    public function hasPlans(): bool
    {
        return !$this->isEmpty();
    }

    public function getPlanCount(): int
    {
        return count($this->plans);
    }

    public function getPlans(): array
    {
        return $this->plans;
    }
}
