<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\GetTransactionHistoryQuery;
use App\Repository\PaymentTransactionRepository;
use Exception;
use Psr\Log\LoggerInterface;

final class GetTransactionHistoryQueryHandler
{
    public function __construct(
        private readonly PaymentTransactionRepository $paymentTransactionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(GetTransactionHistoryQuery $query): array
    {
        try {
            $this->logger->info('Processing transaction history query', [
                'transaction_id' => $query->transactionId,
                'customer_email' => $query->customerEmail,
                'status' => $query->transactionStatus,
                'has_date_filters' => $query->startDate !== null || $query->endDate !== null,
            ]);

            // Get transactions based on query filters
            $transactions = $this->getTransactionsByQuery($query);

            // Format transactions for API response
            $transactionData = [];
            foreach ($transactions as $transaction) {
                $transactionData[] = [
                    'uuid' => $transaction->getUuid(),
                    'transaction_id' => $transaction->getTransactionId(),
                    'amount' => $transaction->getAmount(),
                    'currency_code' => $transaction->getCurrencyCode(),
                    'payment_status' => $transaction->getPaymentStatus(),
                    'last4_digits' => $transaction->getLast4Digits(),
                    'created_at' => $transaction->getCreatedAt()?->format('Y-m-d H:i:s'),
                ];
            }

            $this->logger->info('Transaction history query completed', [
                'transaction_count' => count($transactionData),
                'transaction_id_filter' => $query->transactionId,
            ]);

            return $transactionData;

        } catch (Exception $e) {
            $this->logger->error('Transaction history query failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $query->transactionId,
            ]);

            throw new \RuntimeException('Failed to retrieve transaction history: ' . $e->getMessage());
        }
    }

    private function getTransactionsByQuery(GetTransactionHistoryQuery $query): array
    {
        if ($query->transactionId) {
            $entityManager = $this->paymentTransactionRepository->getEntityManager();
            return $entityManager
                ->createQuery(sprintf("SELECT t FROM App\Entity\PaymentTransaction t WHERE t.transaction_id = '%s'", $query->transactionId))
                ->getResult();
        }

        return $this->paymentTransactionRepository->findAll();
    }
}
