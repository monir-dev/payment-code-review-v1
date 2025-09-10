<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use DateTime;

final class RebillTransactionCompletedEvent
{
    public function __construct(
        public readonly string    $transactionId,
        public readonly float     $amount,
        public readonly string    $currencyCode,
        public readonly string    $paymentStatus,
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
            paymentStatus: 'Approved',
            subscriptionId: $subscriptionId,
            createdAt: new DateTime()
        );
    }
}
