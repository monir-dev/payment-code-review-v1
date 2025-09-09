<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Billing\Event\PlanActivatedEvent;
use App\Domain\Billing\Event\PlanCreatedEvent;
use App\Domain\Billing\Event\PlanDeactivatedEvent;
use Psr\Log\LoggerInterface;

final class PlanEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function onPlanCreated(PlanCreatedEvent $event): void
    {
        $this->logger->info('New plan created', [
            'plan_id' => $event->getPlanId()->getValue(),
            'plan_name' => $event->getName(),
            'amount' => $event->getAmount()->format(),
            'frequency' => $event->getBillingCycle()->getFrequency(),
            'day_frequency' => $event->getBillingCycle()->getDayFrequency(),
            'occurred_on' => $event->getOccurredOn()->format('Y-m-d H:i:s')
        ]);
    }

    public function onPlanActivated(PlanActivatedEvent $event): void
    {
        $this->logger->info('Plan activated', [
            'plan_id' => $event->getPlanId()->getValue(),
            'occurred_on' => $event->getOccurredOn()->format('Y-m-d H:i:s')
        ]);
    }

    public function onPlanDeactivated(PlanDeactivatedEvent $event): void
    {
        $this->logger->info('Plan deactivated', [
            'plan_id' => $event->getPlanId()->getValue(),
            'occurred_on' => $event->getOccurredOn()->format('Y-m-d H:i:s')
        ]);
    }
}
