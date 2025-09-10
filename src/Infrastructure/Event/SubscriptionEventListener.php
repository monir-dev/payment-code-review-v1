<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Subscription\Event\SubscriptionCancelledEvent;
use App\Domain\Subscription\Event\SubscriptionCreatedEvent;
use Psr\Log\LoggerInterface;

final class SubscriptionEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function onSubscriptionCreated(SubscriptionCreatedEvent $event): void
    {
        $this->logger->info('New subscription created', [
            'subscription_id' => $event->getSubscriptionId()->getValue(),
            'plan_id' => $event->getPlanId()->getValue(),
            'customer_email' => $event->getCustomerEmail()->getValue(),
            'amount' => $event->getAmount()->format(),
            'start_date' => $event->getStartDate()->format('Y-m-d'),
            'occurred_on' => $event->getOccurredOn()->format('Y-m-d H:i:s')
        ]);
    }

    public function onSubscriptionCancelled(SubscriptionCancelledEvent $event): void
    {
        $this->logger->info('Subscription cancelled', [
            'subscription_id' => $event->getSubscriptionId()->getValue(),
            'reason' => $event->getReason(),
            'occurred_on' => $event->getOccurredOn()->format('Y-m-d H:i:s')
        ]);
    }
}
