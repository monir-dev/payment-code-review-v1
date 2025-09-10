<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

final class PaymentCompletedSuccessfullyEvent
{
    public function __construct(
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $customerEmail,
        public readonly array $billingInformation,
        public readonly bool $hasSubscriptionData,
        public readonly ?array $subscriptionData = null,
    ) {
    }
}
