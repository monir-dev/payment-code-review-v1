<?php

declare(strict_types=1);

namespace App\Application\Response\Gateway;

use App\Domain\Shared\ValueObject\Money;

final class CompleteTransactionResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $transactionId,
        public readonly ?string $declineMessage = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $rawResponse = null,
        public readonly ?Money $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $tokenId = null,
        public readonly ?array $billingInfo = null,
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
        return $this->status === 'error';
    }

    public function hasTransactionId(): bool
    {
        return !empty($this->transactionId);
    }
}
