<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Repository\PaymentRepositoryInterface;
use App\Domain\Payment\ValueObject\TransactionId;
use App\Infrastructure\Event\DomainEventPublisher;
use App\Infrastructure\Persistence\PaymentEntityMapper;
use App\Infrastructure\Persistence\Entity\PaymentEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PaymentRepository extends ServiceEntityRepository implements PaymentRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PaymentEntityMapper $mapper,
        private readonly DomainEventPublisher $eventPublisher
    ) {
        parent::__construct($registry, PaymentEntity::class);
    }

    public function save(Payment $payment): void
    {
        $entity = $this->mapper->toEntity($payment);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($entity);
        $entityManager->flush();

        // Publish domain events after successful persistence
        $this->eventPublisher->publishEventsFor($payment);
    }

    public function findByTransactionId(TransactionId $transactionId): ?Payment
    {
        $entity = $this->findOneBy(['transactionId' => $transactionId->getValue()]);

        if (!$entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findAll(): array
    {
        $entities = parent::findAll();

        return array_map(
            fn(PaymentEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    public function findByTransactionIds(array $transactionIds): array
    {
        $transactionIdStrings = array_map(
            fn(TransactionId $id) => $id->getValue(),
            $transactionIds
        );

        $entities = $this->createQueryBuilder('p')
            ->where('p.transactionId IN (:transactionIds)')
            ->setParameter('transactionIds', $transactionIdStrings)
            ->getQuery()
            ->getResult();

        return array_map(
            fn(PaymentEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }
}
