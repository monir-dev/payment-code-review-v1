<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * Find active subscriptions
     */
    public function findActiveSubscriptions(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('s.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subscriptions by customer email
     */
    public function findByCustomerEmail(string $email): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.customer_email = :email')
            ->setParameter('email', $email)
            ->orderBy('s.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subscriptions due for charging
     */
    public function findDueForCharging(\DateTime $date = null): array
    {
        if ($date === null) {
            $date = new \DateTime();
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.next_charge_date <= :date')
            ->setParameter('status', 'active')
            ->setParameter('date', $date)
            ->orderBy('s.next_charge_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->flush();
        }
    }

    public function remove(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->flush();
        }
    }

    public function cancelSubscription(Subscription $entity, bool $flush = false): void
    {
        $entity->setStatus('cancelled');
        $entity->setUpdatedAt(new \DateTime());

        if ($flush) {
            $this->flush();
        }
    }

    /**
     * Update subscription after rebilling processing
     */
    public function updateAfterRebilling(Subscription $subscription, \DateTime $nextChargeDate, bool $flush = false): void
    {
        $subscription->setNextChargeDate($nextChargeDate);
        $subscription->setUpdatedAt(new \DateTime());

        if ($flush) {
            $this->flush();
        }
    }

    public function updateStatus(Subscription $subscription, string $status, bool $flush = false): void
    {
        $subscription->setStatus($status);
        $subscription->setUpdatedAt(new \DateTime());

        if ($flush) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
