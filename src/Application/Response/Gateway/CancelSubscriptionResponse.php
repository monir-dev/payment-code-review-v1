<?php

declare(strict_types=1);

namespace App\Application\Response\Gateway;

final class CancelSubscriptionResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly bool $alreadyCancelled = false,
        public readonly ?array $rawResponse = null
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success' || $this->alreadyCancelled;
    }

    public function isFailed(): bool
    {
        return $this->status === 'error' || $this->status === 'failed';
    }
}
