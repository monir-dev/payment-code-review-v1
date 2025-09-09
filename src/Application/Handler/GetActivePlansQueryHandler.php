<?php

namespace App\Application\Handler;

use App\Application\Query\GetActivePlansQuery;
use App\Domain\Billing\Repository\PlanRepositoryInterface;

class GetActivePlansQueryHandler
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository
    ) {
    }

    /**
     * @return array<\App\Domain\Billing\Entity\Plan>
     */
    public function handle(GetActivePlansQuery $query): array
    {
        return $this->planRepository->findActivePlans();
    }
}
