<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\GetDashboardStatsQuery;
use App\Application\Response\DashboardStatsData;
use App\Application\Response\GetDashboardStatsResponse;
use App\Domain\Payment\ValueObject\PaymentStatus;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Repository\PaymentTransactionRepository;
use Exception;
use Psr\Log\LoggerInterface;

final class GetDashboardStatsQueryHandler
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(GetDashboardStatsQuery $query): GetDashboardStatsResponse
    {
        try {
            // Get basic statistics
            $allSubscriptions = $this->subscriptionRepository->findAll();
            $activeSubscriptions = array_filter($allSubscriptions, fn($sub) => $sub->getStatus()->isActive());
            $cancelledSubscriptions = array_filter($allSubscriptions, fn($sub) => $sub->getStatus()->isCancelled());
            
            // Get recent transactions
            $recentTransactions = $this->transactionRepository->findBy(
                [], 
                ['createdAt' => 'DESC'], 
                $query->recentTransactionsLimit
            );
            
            // Calculate total revenue from approved transactions
            $approvedTransactions = $this->transactionRepository->findWithFilters(
                status: PaymentStatus::approved()
            );
            
            $totalRevenue = array_sum(array_map(fn($t) => $t->getAmount(), $approvedTransactions));
            
            // Get refund statistics
            $refundedTransactions = $this->transactionRepository->findWithFilters(
                status: PaymentStatus::refunded()
            );
            
            $partiallyRefundedTransactions = $this->transactionRepository->findWithFilters(
                status: PaymentStatus::partiallyRefunded()
            );

            $stats = new DashboardStatsData(
                totalSubscriptions: count($allSubscriptions),
                activeSubscriptions: count($activeSubscriptions),
                cancelledSubscriptions: count($cancelledSubscriptions),
                totalTransactions: count($recentTransactions),
                totalRevenue: $totalRevenue,
                refundedTransactions: count($refundedTransactions),
                partiallyRefundedTransactions: count($partiallyRefundedTransactions),
            );

            return new GetDashboardStatsResponse(
                stats: $stats,
                recentTransactions: $recentTransactions,
                activeSubscriptions: array_slice($activeSubscriptions, 0, $query->activeSubscriptionsLimit),
            );

        } catch (Exception $e) {
            $this->logger->error('Dashboard stats query failed', [
                'error' => $e->getMessage(),
                'query' => [
                    'recent_transactions_limit' => $query->recentTransactionsLimit,
                    'active_subscriptions_limit' => $query->activeSubscriptionsLimit,
                ]
            ]);

            throw new \RuntimeException('Failed to retrieve dashboard statistics: ' . $e->getMessage());
        }
    }
}
