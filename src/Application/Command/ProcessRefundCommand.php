<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Shared\ValueObject\Money;

final class ProcessRefundCommand
{
    public function __construct(
        public readonly string $transactionId,
        public readonly Money $refundAmount,
    ) {
    }
}
