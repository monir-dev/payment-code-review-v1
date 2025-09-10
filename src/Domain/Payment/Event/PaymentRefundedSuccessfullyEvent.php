<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use DateTimeImmutable;

final class PaymentRefundedSuccessfullyEvent
{
    public function __construct(
        public readonly string $originalTransactionId,
        public readonly string $refundTransactionId,
        public readonly float $refundAmount,
        public readonly string $currency,
        public readonly DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }
}
