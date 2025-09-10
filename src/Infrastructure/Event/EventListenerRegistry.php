<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Payment\Event\PaymentApprovedEvent;
use App\Domain\Payment\Event\PaymentCompletedSuccessfullyEvent;
use App\Domain\Payment\Event\PaymentRefundedEvent;
use App\Domain\Payment\Event\TransactionCompletedEvent;
use App\Domain\Payment\Event\RebillTransactionCompletedEvent;
use App\Domain\Billing\Event\PlanActivatedEvent;
use App\Domain\Billing\Event\PlanCreatedEvent;
use App\Domain\Billing\Event\PlanDeactivatedEvent;
use App\Domain\Subscription\Event\SubscriptionCancelledEvent;
use App\Domain\Subscription\Event\SubscriptionCreatedEvent;

final class EventListenerRegistry
{
    public function __construct(
        private readonly DomainEventBus $eventBus,
        private readonly PaymentEventListener $paymentListener,
        private readonly SubscriptionEventListener $subscriptionListener,
        private readonly PlanEventListener $planListener,
        private readonly TransactionCompletedEventHandler $transactionCompletedHandler,
        private readonly RebillTransactionCompletedEventHandler $rebillTransactionCompletedHandler,
        private readonly PaymentCompletedEventListener $paymentCompletedListener,
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

        // Payment completion event listener (subscription creation)
        $this->eventBus->subscribe(
            PaymentCompletedSuccessfullyEvent::class,
            [$this->paymentCompletedListener, 'handle']
        );

        // Transaction event listeners (database operations)
        $this->eventBus->subscribe(
            TransactionCompletedEvent::class,
            [$this->transactionCompletedHandler, 'handle']
        );

        $this->eventBus->subscribe(
            RebillTransactionCompletedEvent::class,
            [$this->rebillTransactionCompletedHandler, 'handle']
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
