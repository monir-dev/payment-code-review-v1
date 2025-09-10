<?php

declare(strict_types=1);

namespace App\Domain\Payment\Service;

use App\Domain\Payment\Event\PaymentRefundedSuccessfullyEvent;
use App\Domain\Payment\Exception\InvalidRefundAmountException;
use App\Domain\Payment\ValueObject\PaymentStatus;
use App\Entity\PaymentTransaction;

final class RefundProcessingService
{
    public function processRefund(
        PaymentTransaction $originalTransaction,
        PaymentRefundedSuccessfullyEvent $refundEvent
    ): RefundProcessingResult {

        $this->validateRefundAmount($originalTransaction, $refundEvent);

        $isPartialRefund = $this->isPartialRefund($originalTransaction, $refundEvent);
        $newStatus = $this->determineRefundStatus($isPartialRefund);

        return new RefundProcessingResult(
            newStatus: $newStatus,
            shouldCancelSubscription: $this->shouldCancelSubscription($originalTransaction),
            isPartialRefund: $isPartialRefund,
            originalAmount: $originalTransaction->getAmount(),
            refundAmount: $refundEvent->refundAmount
        );
    }

    private function validateRefundAmount(
        PaymentTransaction $originalTransaction,
        PaymentRefundedSuccessfullyEvent $refundEvent
    ): void {
        if ($refundEvent->refundAmount > $originalTransaction->getAmount()) {
            throw new InvalidRefundAmountException(
                sprintf(
                    'Refund amount ($%.2f) cannot exceed original transaction amount ($%.2f) for transaction %s',
                    $refundEvent->refundAmount,
                    $originalTransaction->getAmount(),
                    $refundEvent->originalTransactionId
                )
            );
        }

        if ($refundEvent->refundAmount <= 0) {
            throw new InvalidRefundAmountException(
                sprintf(
                    'Refund amount must be greater than zero, got $%.2f for transaction %s',
                    $refundEvent->refundAmount,
                    $refundEvent->originalTransactionId
                )
            );
        }
    }

    private function isPartialRefund(
        PaymentTransaction $originalTransaction,
        PaymentRefundedSuccessfullyEvent $refundEvent
    ): bool {
        return $refundEvent->refundAmount < $originalTransaction->getAmount();
    }

    private function determineRefundStatus(bool $isPartialRefund): PaymentStatus
    {
        return $isPartialRefund
            ? PaymentStatus::partiallyRefunded()
            : PaymentStatus::refunded();
    }

    private function shouldCancelSubscription(PaymentTransaction $originalTransaction): bool
    {
        return $originalTransaction->getSubscriptionId() !== null;
    }
}
