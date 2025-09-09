<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Event;

use App\Domain\Billing\ValueObject\PlanId;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use DateTimeImmutable;

final class SubscriptionCreatedEvent
{
    public function __construct(
        private readonly SubscriptionId $subscriptionId,
        private readonly PlanId $planId,
        private readonly Email $customerEmail,
        private readonly Money $amount,
        private readonly DateTimeImmutable $startDate,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getPlanId(): PlanId
    {
        return $this->planId;
    }

    public function getCustomerEmail(): Email
    {
        return $this->customerEmail;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
