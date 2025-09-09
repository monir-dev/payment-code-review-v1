<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Billing\Entity\Plan;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Subscription\Entity\Subscription;

final class DomainEventPublisher
{
    public function __construct(
        private readonly DomainEventBus $eventBus
    ) {
    }

    public function publishEventsFor(Payment|Subscription|Plan $aggregate): void
    {
        $events = $aggregate->getUncommittedEvents();
        
        if (empty($events)) {
            return;
        }
        
        $this->eventBus->publishAll($events);
        $aggregate->markEventsAsCommitted();
    }

    /**
     * @param array<Payment|Subscription|Plan> $aggregates
     */
    public function publishEventsForAll(array $aggregates): void
    {
        foreach ($aggregates as $aggregate) {
            $this->publishEventsFor($aggregate);
        }
    }
}
