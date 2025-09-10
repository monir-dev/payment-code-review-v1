<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use App\Domain\Payment\ValueObject\PaymentStatus;
use DateTime;

final class RebillTransactionCompletedEvent
{
    public function __construct(
        public readonly string    $transactionId,
        public readonly float     $amount,
        public readonly string    $currencyCode,
        public readonly PaymentStatus $paymentStatus,
        public readonly string    $subscriptionId,
        public readonly ?DateTime $createdAt = null
    ) {
    }

    public static function fromNmiRebillResponse(array $nmiResponse, string $subscriptionId): self
    {
        return new self(
            transactionId: $nmiResponse['transactionid'] ?? '',
            amount: (float) ($nmiResponse['amount'] ?? 0),
            currencyCode: 'USD', // Default for rebilling
            paymentStatus: PaymentStatus::approved(),
            subscriptionId: $subscriptionId,
            createdAt: new DateTime()
        );
    }
}
