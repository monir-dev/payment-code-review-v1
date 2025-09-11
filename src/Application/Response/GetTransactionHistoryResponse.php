<?php

declare(strict_types=1);

namespace App\Application\Response;

final class GetTransactionHistoryResponse
{
    public function __construct(
        public readonly array $transactions,
        public readonly int $totalCount,
    ) {
    }

    public function isEmpty(): bool
    {
        return empty($this->transactions);
    }

    public function hasTransactions(): bool
    {
        return !$this->isEmpty();
    }

    public function getTransactionCount(): int
    {
        return count($this->transactions);
    }
}
