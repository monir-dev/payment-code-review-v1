<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use App\Domain\Shared\ValueObject\Money;

final class PaymentCompletedSuccessfullyEvent
{
    public function __construct(
        public readonly string $transactionId,
        public readonly Money $amount,
        public readonly string $customerEmail,
        public readonly array $billingInformation,
        public readonly bool $hasSubscriptionData,
        public readonly ?array $subscriptionData = null,
    ) {
    }
}
