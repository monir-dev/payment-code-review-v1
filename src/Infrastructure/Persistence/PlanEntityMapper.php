<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Entity\Plan;
use App\Domain\Billing\ValueObject\PlanId;
use App\Domain\Billing\ValueObject\PlanStatus;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Subscription\ValueObject\BillingCycle;
use App\Infrastructure\Persistence\Entity\PlanEntity;

final class PlanEntityMapper
{
    public function toDomain(PlanEntity $entity): Plan
    {
        // Create value objects (default to USD since currency is not stored in legacy schema)
        $planId = PlanId::fromString($entity->getPlanId());
        $money = Money::fromFloat($entity->getAmount(), 'USD');
        $billingCycle = BillingCycle::custom($entity->getFrequency(), $entity->getDayFrequency());
        $status = PlanStatus::fromString($entity->getStatus());

        // Create domain entity using constructor (since it's persisted data)
        return new Plan(
            $planId,
            $entity->getPlanName(),
            $money,
            $billingCycle,
            $status,
            $entity->getCreatedAt()
        );
    }

    public function toEntity(Plan $domain): PlanEntity
    {
        $entity = new PlanEntity();
        
        // Map basic properties (currency is not stored in legacy schema)
        $entity->setPlanId($domain->getPlanId()->getValue());
        $entity->setPlanName($domain->getName());
        $entity->setAmount($domain->getAmount()->getAmount());
        $entity->setFrequency($domain->getBillingCycle()->getFrequency());
        $entity->setDayFrequency($domain->getBillingCycle()->getDayFrequency());
        $entity->setStatus($domain->getStatus()->getValue());
        $entity->setCreatedAt($domain->getCreatedAt());
        $entity->setUpdatedAt(new \DateTimeImmutable()); // Set current time for updatedAt
        
        return $entity;
    }
}
