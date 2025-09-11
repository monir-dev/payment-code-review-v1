<?php

namespace App\Application\Handler;

use App\Application\Query\GetActivePlansQuery;
use App\Application\Response\GetActivePlansQueryResponse;
use App\Domain\Billing\Repository\PlanRepositoryInterface;

class GetActivePlansQueryHandler
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository
    ) {
    }

    public function handle(GetActivePlansQuery $query): GetActivePlansQueryResponse
    {
        return new GetActivePlansQueryResponse(
            plans: $this->planRepository->findActivePlans()
        );
    }
}
