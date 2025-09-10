<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Repository\PaymentTransactionRepository;
use Psr\Log\LoggerInterface;

final class TransactionLinkingService
{
    public function __construct(
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

     public function linkTransactionToSubscription(string $transactionId, string $subscriptionId): bool
    {
        try {
            $result = $this->transactionRepository->linkToSubscription($transactionId, $subscriptionId);

            if (!$result) {
                $this->logger->warning('Failed to link transaction to subscription', [
                    'transaction_id' => $transactionId,
                    'subscription_id' => $subscriptionId
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to link transaction to subscription', [
                'transaction_id' => $transactionId,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
