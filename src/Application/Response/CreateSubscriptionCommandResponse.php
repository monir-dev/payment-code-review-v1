<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Domain\Shared\ValueObject\Money;

final class CreateSubscriptionCommandResponse
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $status,
        public readonly string $customerVaultId,
        public readonly string $customerEmail,
        public readonly string $planId,
        public readonly string $planName,
        public readonly Money $amount,
        public readonly string $frequency,
        public readonly string $startDate,
        public readonly string $nextChargeDate,
        public readonly array $events = [],
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFormattedAmount(): string
    {
        return '$' . number_format($this->amount->getAmount(), 2);
    }
}
