<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\ValueObject\TransactionId;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;
    
    public function findByTransactionId(TransactionId $transactionId): ?Payment;
    
    /**
     * @return Payment[]
     */
    public function findAll(): array;
    
    /**
     * @param TransactionId[] $transactionIds
     * @return Payment[]
     */
    public function findByTransactionIds(array $transactionIds): array;
}
