<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use App\Domain\Payment\ValueObject\TransactionId;
use DateTimeImmutable;

final class PaymentDeclinedEvent
{
    public function __construct(
        private readonly TransactionId $transactionId,
        private readonly string $reason,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
    }

    public function getTransactionId(): TransactionId
    {
        return $this->transactionId;
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
