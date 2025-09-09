<?php

declare(strict_types=1);

namespace App\Application\Command;

final class CancelSubscriptionCommand
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $reason = 'Customer request',
        public readonly bool $cancelWithGateway = true,
        public readonly ?string $cancelledBy = null
    ) {
    }
}
