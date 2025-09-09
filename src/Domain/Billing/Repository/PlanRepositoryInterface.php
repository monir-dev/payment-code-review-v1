<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repository;

use App\Domain\Billing\Entity\Plan;
use App\Domain\Billing\ValueObject\PlanId;
use App\Domain\Billing\ValueObject\PlanStatus;

interface PlanRepositoryInterface
{
    public function save(Plan $plan): void;
    
    public function findByPlanId(PlanId $planId): ?Plan;
    
    /**
     * @return Plan[]
     */
    public function findByStatus(PlanStatus $status): array;
    
    /**
     * @return Plan[]
     */
    public function findActivePlans(): array;
    
    /**
     * @return Plan[]
     */
    public function findAll(): array;
}
