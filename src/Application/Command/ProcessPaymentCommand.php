<?php

declare(strict_types=1);

namespace App\Application\Command;

final class ProcessPaymentCommand
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currencyCode,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $street1,
        public readonly ?string $street2,
        public readonly string $city,
        public readonly string $state,
        public readonly string $postalCode,
        public readonly string $country,
        public readonly ?string $phone = null,
        public readonly ?string $gatewayToken = null
    ) {
    }
}
