<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Domain\Shared\ValueObject\Money;

final class CreatePlanCommandResponse
{
    public function __construct(
        public readonly string $planId,
        public readonly string $planName,
        public readonly Money $amount,
        public readonly string $frequency,
        public readonly int $dayFrequency,
        public readonly string $status,
        public readonly ?array $nmiResult = null,
        public readonly array $events = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->nmiResult === null || ($this->nmiResult['status'] ?? false);
    }

    public function getNmiPlanId(): ?string
    {
        return $this->nmiResult['plan_id'] ?? null;
    }
}
