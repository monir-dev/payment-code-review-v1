<?php

namespace App\Repository;

use App\Entity\PaymentTransaction;
use App\Domain\Payment\Event\TransactionCompletedEvent;
use App\Domain\Payment\Event\RebillTransactionCompletedEvent;
use App\Domain\Payment\ValueObject\PaymentStatus;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PaymentTransaction>
 */
class PaymentTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentTransaction::class);
    }

    public function findBySubscriptionId(string $subscriptionId): array
    {
        return $this->findBy(['subscription_id' => $subscriptionId]);
    }

    public function findOneTimePayments(): array
    {
        return $this->findBy(['subscription_id' => null]);
    }

    public function findSubscriptionTransactions(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.subscription_id IS NOT NULL')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getSubscriptionRevenue(string $subscriptionId): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as total_revenue')
            ->where('t.subscription_id = :subscriptionId')
            ->andWhere('t.amount > 0') // Exclude refunds (negative amounts)
            ->setParameter('subscriptionId', $subscriptionId)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0.0);
    }

    public function getTransactionStats(): array
    {
        $subscriptionCount = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.subscription_id IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $oneTimeCount = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.subscription_id IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'subscription_transactions' => (int) $subscriptionCount,
            'one_time_transactions' => (int) $oneTimeCount,
            'total_transactions' => (int) ($subscriptionCount + $oneTimeCount),
        ];
    }

    public function createFromTransactionCompletedEvent(TransactionCompletedEvent $event): PaymentTransaction
    {
        if (empty($event->transactionId)) {
            throw new \InvalidArgumentException('Transaction ID cannot be empty');
        }

        $transaction = new PaymentTransaction();
        $transaction->setCreatedAt($event->createdAt ?? new DateTime());
        $transaction->setUuid(Uuid::v4()->toString());
        $transaction->setUsedToken($event->usedToken);
        $transaction->setTransactionId($event->transactionId);
        $transaction->setAmount($event->amount);
        $transaction->setCurrencyCode($event->currencyCode);
        $transaction->setPaymentStatus($event->paymentStatus);
        $transaction->setLast4Digits($event->last4Digits);

        if ($event->subscriptionId) {
            $transaction->setSubscriptionId($event->subscriptionId);
        }

        $this->getEntityManager()->persist($transaction);
        $this->getEntityManager()->flush();

        return $transaction;
    }

    public function createFromRebillTransactionCompletedEvent(RebillTransactionCompletedEvent $event): PaymentTransaction
    {
        $transaction = new PaymentTransaction();
        $transaction->setCreatedAt($event->createdAt ?? new DateTime());
        $transaction->setUuid(Uuid::v4()->toString());
        $transaction->setTransactionId($event->transactionId);
        $transaction->setAmount($event->amount);
        $transaction->setCurrencyCode($event->currencyCode);
        $transaction->setPaymentStatus($event->paymentStatus);
        $transaction->setSubscriptionId($event->subscriptionId); // Always linked to subscription

        $this->getEntityManager()->persist($transaction);
        $this->getEntityManager()->flush();

        return $transaction;
    }

    public function findByTransactionId(string $transactionId): ?PaymentTransaction
    {
        return $this->findOneBy(['transaction_id' => $transactionId]);
    }

    public function linkToSubscription(string $transactionId, string $subscriptionId): bool
    {
        $transaction = $this->findByTransactionId($transactionId);

        if (!$transaction) {
            return false;
        }

        // Check if transaction is already linked to a different subscription
        if ($transaction->getSubscriptionId() && $transaction->getSubscriptionId() !== $subscriptionId) {
            return false;
        }

        $transaction->setSubscriptionId($subscriptionId);
        $this->getEntityManager()->flush();

        return true;
    }

    public function unlinkFromSubscription(string $transactionId): bool
    {
        $transaction = $this->findByTransactionId($transactionId);

        if (!$transaction) {
            return false;
        }

        $transaction->setSubscriptionId(null);
        $this->getEntityManager()->flush();

        return true;
    }

    public function findTransactionsForSubscription(string $subscriptionId): array
    {
        return $this->findBySubscriptionId($subscriptionId);
    }

    public function findWithFilters(
        ?string        $transactionId = null,
        ?PaymentStatus $status = null,
        ?DateTime      $startDate = null,
        ?DateTime      $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('t');

        if ($transactionId) {
            $qb->andWhere('t.transaction_id = :transactionId')
               ->setParameter('transactionId', $transactionId);
        }

        if ($status) {
            $qb->andWhere('t.payment_status = :status')
               ->setParameter('status', $status->getValue());
        }

        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->orderBy('t.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    public function updateTransactionStatus(string $transactionId, ?PaymentStatus $status): bool
    {
        $transaction = $this->findByTransactionId($transactionId);

        if (!$transaction) {
            return false;
        }

        $transaction->setPaymentStatus($status);
        $this->getEntityManager()->flush();

        return true;
    }
}
