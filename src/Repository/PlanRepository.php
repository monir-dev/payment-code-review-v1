<?php

namespace App\Repository;

use App\Dto\CreatePlanDto;
use App\Entity\Plan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plan>
 */
class PlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    public function findActivePlans(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('p.plan_name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActivePlansForSubscription(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('p.frequency', 'ASC')
            ->addOrderBy('p.amount', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function findByFrequency(string $frequency): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.frequency = :frequency')
            ->andWhere('p.status = :status')
            ->setParameter('frequency', $frequency)
            ->setParameter('status', 'active')
            ->orderBy('p.amount', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Plan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createFromDto(CreatePlanDto $planDto): Plan
    {
        $plan = new Plan();
        $plan->setPlanId($planDto->planId);
        $plan->setPlanName($planDto->planName);
        $plan->setAmount($planDto->amount);
        $plan->setFrequency($planDto->frequency);
        $plan->setDayFrequency($planDto->dayFrequency);
        $plan->setStatus('active');

        $this->save($plan, true);

        return $plan;
    }

    public function remove(Plan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function toggleStatus(Plan $entity): string
    {
        $newStatus = ($entity->getStatus() === 'active') ? 'inactive' : 'active';

        $entity->setStatus($newStatus);
        $entity->setUpdatedAt(new \DateTime());

        $this->getEntityManager()->flush();

        return $newStatus;
    }
}
