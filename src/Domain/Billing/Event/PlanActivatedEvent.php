<?php

declare(strict_types=1);

namespace App\Domain\Billing\Event;

use App\Domain\Billing\ValueObject\PlanId;
use DateTimeImmutable;

final class PlanActivatedEvent
{
    public function __construct(
        private readonly PlanId $planId,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
    }

    public function getPlanId(): PlanId
    {
        return $this->planId;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
