<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use App\Domain\Payment\ValueObject\PaymentStatus;
use App\Domain\Shared\ValueObject\Money;
use DateTime;

final class RebillTransactionCompletedEvent
{
    public function __construct(
        public readonly string    $transactionId,
        public readonly Money     $amount,
        public readonly PaymentStatus $paymentStatus,
        public readonly string    $subscriptionId,
        public readonly ?DateTime $createdAt = null
    ) {
    }

    public static function fromNmiRebillResponse(array $nmiResponse, string $subscriptionId): self
    {
        return new self(
            transactionId: $nmiResponse['transactionid'] ?? '',
            amount: Money::fromFloat((float) ($nmiResponse['amount'] ?? 0), 'USD'),
            paymentStatus: PaymentStatus::approved(),
            subscriptionId: $subscriptionId,
            createdAt: new DateTime()
        );
    }
}
