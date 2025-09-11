<?php

declare(strict_types=1);

namespace App\Application\Response;

final class ProcessPaymentCommandResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $transactionId,
        public readonly string $amount,
        public readonly array $events = [],
    ) {
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
