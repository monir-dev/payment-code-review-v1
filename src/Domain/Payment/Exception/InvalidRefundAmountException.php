<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exception;

use DomainException;

final class InvalidRefundAmountException extends DomainException
{
    public static function exceedsOriginalAmount(float $refundAmount, float $originalAmount, string $transactionId): self
    {
        return new self(sprintf(
            'Refund amount ($%.2f) cannot exceed original transaction amount ($%.2f) for transaction %s',
            $refundAmount,
            $originalAmount,
            $transactionId
        ));
    }
    
    public static function invalidAmount(float $refundAmount, string $transactionId): self
    {
        return new self(sprintf(
            'Refund amount must be greater than zero, got $%.2f for transaction %s',
            $refundAmount,
            $transactionId
        ));
    }
}
