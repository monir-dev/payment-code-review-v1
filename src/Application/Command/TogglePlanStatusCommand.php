<?php

declare(strict_types=1);

namespace App\Application\Command;

final class TogglePlanStatusCommand
{
    public function __construct(
        public readonly string $planId
    ) {
    }
}
