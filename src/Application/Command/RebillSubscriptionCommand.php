<?php

declare(strict_types=1);

namespace App\Application\Command;

final class RebillSubscriptionCommand
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly ?float $customAmount = null,
        public readonly bool $processWithGateway = true,
        public readonly string $reason = 'Manual rebill',
        public readonly ?string $initiatedBy = null
    ) {
    }
}
