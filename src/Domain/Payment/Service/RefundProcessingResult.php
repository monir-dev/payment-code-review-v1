<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\ValueObject\PaymentStatus;

final class RefundProcessingResult
{
    public function __construct(
        public readonly PaymentStatus $newStatus,
        public readonly bool $shouldCancelSubscription,
        public readonly bool $isPartialRefund,
        public readonly float $originalAmount,
        public readonly float $refundAmount
    ) {
    }

    public function getRefundPercentage(): float
    {
        if ($this->originalAmount <= 0) {
            return 0.0;
        }

        return min(1.0, $this->refundAmount / $this->originalAmount);
    }

    public function isFullRefund(): bool
    {
        return !$this->isPartialRefund;
    }

    public function getRemainingAmount(): float
    {
        return max(0.0, $this->originalAmount - $this->refundAmount);
    }
}
