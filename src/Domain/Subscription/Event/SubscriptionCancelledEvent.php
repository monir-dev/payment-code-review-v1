<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Event;

use App\Domain\Subscription\ValueObject\SubscriptionId;
use DateTimeImmutable;

final class SubscriptionCancelledEvent
{
    public function __construct(
        private readonly SubscriptionId $subscriptionId,
        private readonly string $reason,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
