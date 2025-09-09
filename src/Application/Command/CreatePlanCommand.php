<?php

declare(strict_types=1);

namespace App\Application\Command;

final class CreatePlanCommand
{
    public function __construct(
        public readonly string $planName,
        public readonly float $amount,
        public readonly string $currencyCode,
        public readonly string $frequency
    ) {
    }
}
