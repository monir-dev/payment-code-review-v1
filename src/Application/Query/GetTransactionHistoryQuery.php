<?php

declare(strict_types=1);

namespace App\Application\Query;

use DateTimeInterface;

final class GetTransactionHistoryQuery
{
    public function __construct(
        public readonly ?string            $transactionId = null,
        public readonly ?string            $customerEmail = null,
        public readonly ?string            $transactionStatus = null,
        public readonly ?DateTimeInterface $startDate = null,
        public readonly ?DateTimeInterface $endDate = null,
        public readonly ?int               $limit = null,
        public readonly ?int               $offset = null,
    ) {
    }
}
