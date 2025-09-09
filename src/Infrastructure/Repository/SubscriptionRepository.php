<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Subscription\Entity\Subscription;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use App\Domain\Subscription\ValueObject\SubscriptionStatus;
use App\Infrastructure\Event\DomainEventPublisher;
use App\Infrastructure\Persistence\Entity\SubscriptionEntity;
use App\Infrastructure\Persistence\SubscriptionEntityMapper;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SubscriptionRepository extends ServiceEntityRepository implements SubscriptionRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SubscriptionEntityMapper $mapper,
        private readonly DomainEventPublisher $eventPublisher
    ) {
        parent::__construct($registry, SubscriptionEntity::class);
    }

    public function save(Subscription $subscription): void
    {
        $entityManager = $this->getEntityManager();

        $existingEntity = $this->findOneBy(['subscriptionId' => $subscription->getSubscriptionId()->getValue()]);

        if ($existingEntity) {
            $existingEntity->setStatus($subscription->getStatus()->getValue());
            $existingEntity->setNextChargeDate($subscription->getNextChargeDate());
            $existingEntity->setUpdatedAt(new \DateTimeImmutable());

            $existingEntity->setAmount($subscription->getPlan()->getAmount()->getAmount());
            $existingEntity->setFrequency($subscription->getPlan()->getBillingCycle()->getFrequency());
            $existingEntity->setCurrencyCode($subscription->getAmount()->getCurrency()->getCode());

            $billing = $subscription->getBillingInformation();
            $existingEntity->setCustomerEmail($billing->getEmail()->getValue());
            $existingEntity->setBillingFirstName($billing->getFirstName());
            $existingEntity->setBillingLastName($billing->getLastName());

            $address = $billing->getAddress();
            $existingEntity->setBillingStreet1($address->getStreet1());
            $existingEntity->setBillingStreet2($address->getStreet2());
            $existingEntity->setBillingCity($address->getCity());
            $existingEntity->setBillingState($address->getState());
            $existingEntity->setBillingPostalCode($address->getPostalCode());
            $existingEntity->setBillingCountry($address->getCountry());
            $existingEntity->setBillingPhone($billing->getPhone());
        } else {
            $existingEntity = $this->mapper->toEntity($subscription);
            $entityManager->persist($existingEntity);
        }

        $entityManager->flush();

        $this->eventPublisher->publishEventsFor($subscription);
    }

    public function findBySubscriptionId(SubscriptionId $subscriptionId): ?Subscription
    {
        $entity = $this->findOneBy(['subscriptionId' => $subscriptionId->getValue()]);

        if (!$entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByStatus(SubscriptionStatus $status): array
    {
        $entities = $this->findBy(['status' => $status->getValue()]);

        return array_map(
            fn(SubscriptionEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    public function findByCustomerEmail(Email $email): array
    {
        $entities = $this->createQueryBuilder('s')
            ->where('s.customerEmail = :email')
            ->setParameter('email', $email->getValue())
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(SubscriptionEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    public function findByOriginalTransactionId(TransactionId $transactionId): array
    {
        $entities = $this->createQueryBuilder('s')
            ->where('s.originalTransactionId = :transactionId')
            ->andWhere('s.status = :status')
            ->setParameter('transactionId', $transactionId->getValue())
            ->setParameter('status', 'active')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(SubscriptionEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    public function findDueForCharging(?DateTimeImmutable $date = null): array
    {
        if ($date === null) {
            $date = new DateTimeImmutable();
        }

        $entities = $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.nextChargeDate <= :date')
            ->setParameter('status', 'active')
            ->setParameter('date', $date)
            ->orderBy('s.nextChargeDate', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(SubscriptionEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }

    public function findAll(): array
    {
        $entities = parent::findAll();

        return array_map(
            fn(SubscriptionEntity $entity) => $this->mapper->toDomain($entity),
            $entities
        );
    }
}
