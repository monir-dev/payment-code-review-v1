<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;

final class PaymentRefundedEvent
{
    public function __construct(
        private readonly TransactionId $originalTransactionId,
        private readonly TransactionId $refundTransactionId,
        private readonly Money $refundAmount,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
    }

    public function getOriginalTransactionId(): TransactionId
    {
        return $this->originalTransactionId;
    }

    public function getRefundTransactionId(): TransactionId
    {
        return $this->refundTransactionId;
    }

    public function getRefundAmount(): Money
    {
        return $this->refundAmount;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
