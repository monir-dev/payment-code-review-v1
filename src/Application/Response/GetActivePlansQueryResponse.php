<?php

declare(strict_types=1);

namespace App\Application\Response;

final class GetActivePlansQueryResponse
{
    /**
     * @param array $plans Array of Plan entities/data
     */
    public function __construct(
        public readonly array $plans
    ) {
    }

    public function getPlans(): array
    {
        return $this->plans;
    }

    public function hasPlans(): bool
    {
        return !empty($this->plans);
    }

    public function getCount(): int
    {
        return count($this->plans);
    }
}