<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Shared\ValueObject\Money;

final class CreatePlanCommand
{
    public function __construct(
        public readonly string $planName,
        public readonly Money $amount,
        public readonly string $currencyCode,
        public readonly string $frequency
    ) {
    }
}
