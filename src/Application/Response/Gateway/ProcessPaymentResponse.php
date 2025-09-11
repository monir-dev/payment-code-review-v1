<?php

declare(strict_types=1);

namespace App\Application\Response\Gateway;

use App\Domain\Shared\ValueObject\Money;

final class ProcessPaymentResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $reason = null,
        public readonly ?string $message = null,
        public readonly ?Money $amount = null,
        public readonly ?string $currency = null,
        public readonly ?array $rawResponse = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function hasTransactionId(): bool
    {
        return !empty($this->transactionId);
    }
}
