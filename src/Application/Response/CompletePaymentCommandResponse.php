<?php

declare(strict_types=1);

namespace App\Application\Response;

final class CompletePaymentCommandResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $transactionId,
        public readonly ?string $message = null,
        public readonly ?string $declineMessage = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $subscriptionCreated = false,
        public readonly ?string $subscriptionId = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function isFailed(): bool
    {
        return !in_array($this->status, ['success', 'declined'], true);
    }

    public function hasSubscription(): bool
    {
        return $this->subscriptionCreated && !empty($this->subscriptionId);
    }
}
