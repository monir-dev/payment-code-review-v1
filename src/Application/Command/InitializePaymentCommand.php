<?php

declare(strict_types=1);

namespace App\Application\Command;

final class InitializePaymentCommand
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $redirectUrl,
        public readonly string $billingFirstName,
        public readonly string $billingLastName,
        public readonly string $billingEmail,
        public readonly string $billingAddress1,
        public readonly ?string $billingAddress2,
        public readonly string $billingCity,
        public readonly string $billingState,
        public readonly string $billingPostal,
        public readonly string $billingCountry,
        public readonly ?string $billingPhone,
        public readonly bool $isSubscription = false,
        public readonly ?string $planId = null,
        public readonly ?string $selectedPlanData = null, // JSON encoded plan data for subscription
    ) {
    }
}
