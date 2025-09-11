<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\GetTransactionHistoryQuery;
use App\Application\Response\GetTransactionHistoryResponse;
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

    public function handle(GetTransactionHistoryQuery $query): GetTransactionHistoryResponse
    {
        try {
            // Get transactions based on query filters
            $transactions = $this->paymentTransactionRepository->findWithFilters(
                $query->transactionId,
                $query->transactionStatus,
                $query->startDate,
                $query->endDate
            );

            // Format transactions for API response
            $transactionData = [];
            foreach ($transactions as $transaction) {
                $transactionData[] = [
                    'uuid' => $transaction->getUuid(),
                    'transaction_id' => $transaction->getTransactionId(),
                    'amount' => $transaction->getAmount(),
                    'currency_code' => $transaction->getCurrencyCode(),
                    'payment_status' => $transaction->getPaymentStatus()?->getValue(),
                    'last4_digits' => $transaction->getLast4Digits(),
                    'created_at' => $transaction->getCreatedAt()?->format('Y-m-d H:i:s'),
                ];
            }

            return new GetTransactionHistoryResponse(
                transactions: $transactionData,
                totalCount: count($transactionData),
            );

        } catch (Exception $e) {
            $this->logger->error('Transaction history query failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $query->transactionId,
            ]);

            throw new \RuntimeException('Failed to retrieve transaction history: ' . $e->getMessage());
        }
    }

}
