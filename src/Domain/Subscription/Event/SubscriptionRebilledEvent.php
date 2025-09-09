<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Event;

use App\Domain\Shared\ValueObject\Money;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use DateTimeImmutable;

final class SubscriptionRebilledEvent
{
    public function __construct(
        private readonly SubscriptionId $subscriptionId,
        private readonly Money $amount,
        private readonly string $transactionId,
        private readonly string $reason,
        private readonly DateTimeImmutable $nextChargeDate,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getNextChargeDate(): DateTimeImmutable
    {
        return $this->nextChargeDate;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
