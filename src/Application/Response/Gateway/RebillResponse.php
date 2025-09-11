<?php

declare(strict_types=1);

namespace App\Application\Response\Gateway;

use App\Domain\Shared\ValueObject\Money;

final class RebillResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?Money $amount = null,
        public readonly ?string $message = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?array $rawResponse = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function isFailed(): bool
    {
        return !in_array($this->status, ['success', 'declined'], true);
    }

    public function hasTransactionId(): bool
    {
        return !empty($this->transactionId);
    }
}
