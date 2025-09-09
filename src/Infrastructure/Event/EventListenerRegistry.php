<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Payment\Event\PaymentApprovedEvent;
use App\Domain\Payment\Event\PaymentRefundedEvent;
use App\Domain\Billing\Event\PlanActivatedEvent;
use App\Domain\Billing\Event\PlanCreatedEvent;
use App\Domain\Billing\Event\PlanDeactivatedEvent;
use App\Domain\Subscription\Event\SubscriptionCancelledEvent;
use App\Domain\Subscription\Event\SubscriptionCreatedEvent;
use App\Domain\Subscription\Event\SubscriptionRenewedEvent;
use Psr\Log\LoggerInterface;

final class EventListenerRegistry
{
    public function __construct(
        private readonly DomainEventBus $eventBus,
        private readonly PaymentEventListener $paymentListener,
        private readonly SubscriptionEventListener $subscriptionListener,
        private readonly PlanEventListener $planListener,
        private readonly LoggerInterface $logger
    ) {
        $this->registerListeners();
    }

    private function registerListeners(): void
    {
        // Payment event listeners
        $this->eventBus->subscribe(
            PaymentApprovedEvent::class,
            [$this->paymentListener, 'onPaymentApproved']
        );
        
        $this->eventBus->subscribe(
            PaymentRefundedEvent::class,
            [$this->paymentListener, 'onPaymentRefunded']
        );
        
        // Subscription event listeners
        $this->eventBus->subscribe(
            SubscriptionCreatedEvent::class,
            [$this->subscriptionListener, 'onSubscriptionCreated']
        );
        
        $this->eventBus->subscribe(
            SubscriptionCancelledEvent::class,
            [$this->subscriptionListener, 'onSubscriptionCancelled']
        );
        
        $this->eventBus->subscribe(
            SubscriptionRenewedEvent::class,
            [$this->subscriptionListener, 'onSubscriptionRenewed']
        );
        
        // Plan event listeners
        $this->eventBus->subscribe(
            PlanCreatedEvent::class,
            [$this->planListener, 'onPlanCreated']
        );
        
        $this->eventBus->subscribe(
            PlanActivatedEvent::class,
            [$this->planListener, 'onPlanActivated']
        );
        
        $this->eventBus->subscribe(
            PlanDeactivatedEvent::class,
            [$this->planListener, 'onPlanDeactivated']
        );
    }
}
