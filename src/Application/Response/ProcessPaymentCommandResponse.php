<?php

declare(strict_types=1);

namespace App\Application\Response;

final class ProcessPaymentCommandResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $transactionId,
        public readonly string $amount,
        public readonly array $events = []
    ) {
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['approved', 'completed'], true);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['declined', 'failed', 'error'], true);
    }
}