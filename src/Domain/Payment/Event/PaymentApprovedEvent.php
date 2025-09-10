<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;

final class PaymentApprovedEvent
{
    public function __construct(
        private readonly TransactionId $transactionId,
        private readonly Money $amount,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
    }

    public function getTransactionId(): TransactionId
    {
        return $this->transactionId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
