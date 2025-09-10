<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Billing\Entity\Plan;
use App\Domain\Billing\Repository\PlanRepositoryInterface;
use App\Domain\Billing\ValueObject\PlanId;
use App\Domain\Billing\ValueObject\PlanStatus;
use App\Infrastructure\Persistence\Entity\PlanEntity;
use App\Infrastructure\Event\DomainEventPublisher;
use App\Infrastructure\Event\EventListenerRegistry;
use App\Infrastructure\Persistence\PlanEntityMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PlanRepository extends ServiceEntityRepository implements PlanRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PlanEntityMapper $mapper,
        private readonly DomainEventPublisher $eventPublisher,
    ) {
        parent::__construct($registry, PlanEntity::class);
    }

    public function save(Plan $plan): void
    {
        $entityManager = $this->getEntityManager();

        // Try to find existing entity first
        $existingEntity = $this->findOneBy(['planId' => $plan->getPlanId()->getValue()]);

        if ($existingEntity) {
            // Update existing entity with domain object values
            $existingEntity->setPlanName($plan->getName());
            $existingEntity->setAmount($plan->getAmount()->getAmount());
            $existingEntity->setFrequency($plan->getBillingCycle()->getFrequency());
            $existingEntity->setDayFrequency($plan->getBillingCycle()->getDayFrequency());
            $existingEntity->setStatus($plan->getStatus()->getValue());
            $existingEntity->setUpdatedAt(new \DateTimeImmutable());
            // Note: Don't update createdAt for existing entities
        } else {
            // Create new entity for new plans
            $existingEntity = $this->mapper->toEntity($plan);
            $entityManager->persist($existingEntity);
        }

        $entityManager->flush();

        // Publish domain events after successful persistence
        $this->eventPublisher->publishEventsFor($plan);
    }

    public function findByPlanId(PlanId $planId): ?Plan
    {
        $entity = $this->findOneBy(['planId' => $planId->getValue()]);

        if (!$entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByStatus(PlanStatus $status): array
    {
        $entities = $this->findBy(['status' => $status->getValue()]);

        return array_map(
            fn(PlanEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    /**
     * @return Plan[]
     */
    public function findActivePlans(): array
    {
        $entities = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('p.planName', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(PlanEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    public function findAll(): array
    {
        $entities = parent::findAll();

        return array_map(
            fn(PlanEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    /**
     * Find a plan by database ID (for form processing)
     */
    public function findById(int $id): ?Plan
    {
        $entity = $this->find($id);

        if (!$entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
