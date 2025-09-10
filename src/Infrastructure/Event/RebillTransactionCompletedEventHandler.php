<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Payment\Event\RebillTransactionCompletedEvent;
use App\Repository\PaymentTransactionRepository;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class RebillTransactionCompletedEventHandler
{
    public function __construct(
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(RebillTransactionCompletedEvent $event): void
    {
        try {
            $this->transactionRepository->createFromRebillTransactionCompletedEvent($event);
        } catch (Exception $e) {
            $this->logger->error('Failed to persist rebill transaction from event', [
                'transaction_id' => $event->transactionId,
                'subscription_id' => $event->subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw new RuntimeException(
                'Failed to persist rebill transaction: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
