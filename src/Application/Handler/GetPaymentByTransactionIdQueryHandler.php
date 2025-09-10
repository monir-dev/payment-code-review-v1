<?php

namespace App\Application\Handler;

use App\Application\Query\GetPaymentByTransactionIdQuery;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Repository\PaymentRepositoryInterface;
use App\Domain\Payment\ValueObject\TransactionId;

class GetPaymentByTransactionIdQueryHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository
    ) {
    }

    public function handle(GetPaymentByTransactionIdQuery $query): ?Payment
    {
        $transactionId = TransactionId::fromString($query->transactionId);

        return $this->paymentRepository->findByTransactionId($transactionId);
    }
}
