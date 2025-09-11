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
        public readonly ?array $gatewayResult = null,
        public readonly array $events = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return !empty($this->transactionId);
    }

    public function wasProcessedWithGateway(): bool
    {
        return $this->gatewayProcessed;
    }

    public function getGatewayStatus(): ?string
    {
        return $this->gatewayResult['status'] ?? null;
    }
}
