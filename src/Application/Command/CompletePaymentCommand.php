<?php

declare(strict_types=1);

namespace App\Application\Command;

final class CompletePaymentCommand
{
    public function __construct(
        public readonly string $tokenId,
        public readonly ?float $paymentAmount = null,
        public readonly ?array $subscriptionData = null,
    ) {
    }
}
