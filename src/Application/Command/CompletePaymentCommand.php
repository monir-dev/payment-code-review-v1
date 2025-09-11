<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Shared\ValueObject\Money;

final class CompletePaymentCommand
{
    public function __construct(
        public readonly string $tokenId,
        public readonly ?Money $paymentAmount = null,
        public readonly ?array $subscriptionData = null,
    ) {
    }
}
