<?php

declare(strict_types=1);

namespace App\Application\Response\Gateway;

final class RefundResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?float $refundAmount = null,
        public readonly ?string $originalTransactionId = null,
        public readonly ?string $message = null,
        public readonly ?array $rawResponse = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status !== 'success';
    }

    public function hasTransactionId(): bool
    {
        return !empty($this->transactionId);
    }
}
