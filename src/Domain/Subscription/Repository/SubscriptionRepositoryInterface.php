<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Repository;

use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Subscription\Entity\Subscription;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use App\Domain\Subscription\ValueObject\SubscriptionStatus;
use DateTimeImmutable;

interface SubscriptionRepositoryInterface
{
    public function save(Subscription $subscription): void;
    
    public function findBySubscriptionId(SubscriptionId $subscriptionId): ?Subscription;
    
    /**
     * @return Subscription[]
     */
    public function findByStatus(SubscriptionStatus $status): array;
    
    /**
     * @return Subscription[]
     */
    public function findByCustomerEmail(Email $email): array;
    
    /**
     * @return Subscription[]
     */
    public function findByOriginalTransactionId(TransactionId $transactionId): array;
    
    /**
     * @return Subscription[]
     */
    public function findDueForCharging(?DateTimeImmutable $date = null): array;
    
    /**
     * @return Subscription[]
     */
    public function findAll(): array;
}
