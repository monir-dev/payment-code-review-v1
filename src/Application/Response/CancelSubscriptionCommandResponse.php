<?php

declare(strict_types=1);

namespace App\Application\Response;

final class CancelSubscriptionCommandResponse
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $originalStatus,
        public readonly string $newStatus,
        public readonly string $reason,
        public readonly string $cancelledBy,
        public readonly string $cancelledAt,
        public readonly bool $gatewayProcessed,
        public readonly ?array $gatewayResult = null,
        public readonly array $events = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->newStatus === 'cancelled';
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
