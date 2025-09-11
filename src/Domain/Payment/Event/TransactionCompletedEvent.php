<?php

declare(strict_types=1);

namespace App\Domain\Payment\Event;

use App\Domain\Payment\ValueObject\PaymentStatus;
use App\Domain\Shared\ValueObject\Money;

final class TransactionCompletedEvent
{
    public function __construct(
        public readonly string $transactionId,
        public readonly Money $amount,
        public readonly PaymentStatus $paymentStatus,
        public readonly string $usedToken,
        public readonly string $last4Digits,
        public readonly ?string $subscriptionId = null,
        public readonly ?\DateTime $createdAt = null
    ) {
    }

    public static function fromNmiResponse(array $nmiResponse, ?string $subscriptionId = null): self
    {
        $ccNumber = $nmiResponse['billing']['cc-number'] ?? '';
        $last4Digits = $ccNumber ? substr($ccNumber, -4) : '0000';

        return new self(
            transactionId: $nmiResponse['transaction-id'] ?? '',
            amount: Money::fromFloat((float) ($nmiResponse['amount'] ?? 0), $nmiResponse['currency'] ?? 'USD'),
            paymentStatus: PaymentStatus::approved(),
            usedToken: $nmiResponse['token-id'] ?? '',
            last4Digits: $last4Digits,
            subscriptionId: $subscriptionId,
            createdAt: new \DateTime()
        );
    }
}
