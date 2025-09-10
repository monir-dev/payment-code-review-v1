<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Payment\Event\PaymentApprovedEvent;
use App\Domain\Payment\Event\PaymentRefundedEvent;
use Psr\Log\LoggerInterface;

final class PaymentEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function onPaymentApproved(PaymentApprovedEvent $event): void
    {
        $this->logger->info('Payment approved successfully', [
            'transaction_id' => $event->getTransactionId()->getValue(),
            'amount' => $event->getAmount()->format(),
            'occurred_on' => $event->getOccurredOn()->format('Y-m-d H:i:s')
        ]);
    }

    public function onPaymentRefunded(PaymentRefundedEvent $event): void
    {
        $this->logger->info('Payment refunded successfully', [
            'original_transaction_id' => $event->getOriginalTransactionId()->getValue(),
            'refund_transaction_id' => $event->getRefundTransactionId()->getValue(),
            'refund_amount' => $event->getRefundAmount()->format(),
            'occurred_on' => $event->getOccurredOn()->format('Y-m-d H:i:s')
        ]);
    }
}
