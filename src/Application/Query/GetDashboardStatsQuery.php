<?php

declare(strict_types=1);

namespace App\Application\Query;

final class GetDashboardStatsQuery
{
    public function __construct(
        public readonly ?int $recentTransactionsLimit = 10,
        public readonly ?int $activeSubscriptionsLimit = 5,
    ) {
    }
}
