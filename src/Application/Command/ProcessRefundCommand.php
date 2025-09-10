<?php

declare(strict_types=1);

namespace App\Application\Command;

final class ProcessRefundCommand
{
    public function __construct(
        public readonly string $transactionId,
        public readonly float $refundAmount,
    ) {
    }
}
