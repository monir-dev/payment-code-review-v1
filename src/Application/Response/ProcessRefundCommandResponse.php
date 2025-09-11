<?php

declare(strict_types=1);

namespace App\Application\Response;

final class ProcessRefundCommandResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $transactionId,
        public readonly float $refundAmount,
        public readonly string $originalTransactionId,
        public readonly ?string $message = null,
        public readonly bool $subscriptionCancelled = false,
        public readonly ?string $subscriptionId = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function hasSubscriptionCancellation(): bool
    {
        return $this->subscriptionCancelled;
    }
}
