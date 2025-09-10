<?php

namespace App\Application\Query;

class GetPaymentByTransactionIdQuery
{
    public function __construct(
        public readonly string $transactionId
    ) {
    }
}
