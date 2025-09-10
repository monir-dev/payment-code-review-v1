<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Payment\Event\TransactionCompletedEvent;
use App\Repository\PaymentTransactionRepository;
use Exception;
use Psr\Log\LoggerInterface;

final class TransactionCompletedEventHandler
{
    public function __construct(
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(TransactionCompletedEvent $event): void
    {
        try {
            $this->transactionRepository->createFromTransactionCompletedEvent($event);

        } catch (Exception $e) {
            $this->logger->error('Failed to persist transaction from event', [
                'transaction_id' => $event->transactionId,
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException(
                'Failed to persist transaction: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
