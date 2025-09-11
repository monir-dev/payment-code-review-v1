<?php

declare(strict_types=1);

namespace App\Application\Response;

final class DashboardStatsData
{
    public function __construct(
        public readonly int $totalSubscriptions,
        public readonly int $activeSubscriptions,
        public readonly int $cancelledSubscriptions,
        public readonly int $totalTransactions,
        public readonly float $totalRevenue,
        public readonly int $refundedTransactions,
        public readonly int $partiallyRefundedTransactions,
    ) {
    }

    public function getTotalRefunds(): int
    {
        return $this->refundedTransactions + $this->partiallyRefundedTransactions;
    }

    public function getSubscriptionActivity(): float
    {
        if ($this->totalSubscriptions === 0) {
            return 0.0;
        }

        return ($this->activeSubscriptions / $this->totalSubscriptions) * 100;
    }
}
