<?php

declare(strict_types=1);

namespace App\Application\Command;

use DateTimeImmutable;

final class CreateSubscriptionCommand
{
    public function __construct(
        public readonly string $planId,
        public readonly string $customerEmail,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $street1,
        public readonly ?string $street2,
        public readonly string $city,
        public readonly string $state,
        public readonly string $postalCode,
        public readonly string $country,
        public readonly ?string $phone = null,
        public readonly ?DateTimeImmutable $startDate = null,
        public readonly ?string $customerVaultId = null,
        public readonly ?string $originalTransactionId = null
    ) {
    }
}
