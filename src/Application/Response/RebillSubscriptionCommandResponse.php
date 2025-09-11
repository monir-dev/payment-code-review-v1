<?php

declare(strict_types=1);

namespace App\Application\Response;

final class RebillSubscriptionCommandResponse
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $status,
        public readonly string $transactionId,
        public readonly string $amount,
        public readonly string $reason,
        public readonly string $nextChargeDate,
        public readonly string $rebilledAt,
        public readonly bool $gatewayProcessed,
        public readonly array $events = [],
        public readonly ?array $gatewayResult = null
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'active';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['cancelled', 'failed', 'suspended'], true);
    }

    public function wasGatewayProcessed(): bool
    {
        return $this->gatewayProcessed;
    }
}