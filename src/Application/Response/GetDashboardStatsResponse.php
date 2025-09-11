<?php

declare(strict_types=1);

namespace App\Application\Response;

final class GetDashboardStatsResponse
{
    public function __construct(
        public readonly DashboardStatsData $stats,
        public readonly array $recentTransactions,
        public readonly array $activeSubscriptions,
    ) {
    }
}
