<?php

declare(strict_types=1);

namespace App\Domain\Billing\Event;

use App\Domain\Billing\ValueObject\PlanId;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Subscription\ValueObject\BillingCycle;
use DateTimeImmutable;

final class PlanCreatedEvent
{
    public function __construct(
        private readonly PlanId $planId,
        private readonly string $name,
        private readonly Money $amount,
        private readonly BillingCycle $billingCycle,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
    }

    public function getPlanId(): PlanId
    {
        return $this->planId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getBillingCycle(): BillingCycle
    {
        return $this->billingCycle;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
