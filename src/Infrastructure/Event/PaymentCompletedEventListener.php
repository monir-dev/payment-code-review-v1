<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Application\Command\CreateSubscriptionCommand;
use App\Application\Handler\CreateSubscriptionCommandHandler;
use App\Application\Service\TransactionLinkingService;
use App\Domain\Payment\Event\PaymentCompletedSuccessfullyEvent;
use Psr\Log\LoggerInterface;

final class PaymentCompletedEventListener
{
    public function __construct(
        private readonly CreateSubscriptionCommandHandler $createSubscriptionHandler,
        private readonly TransactionLinkingService $transactionLinkingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(PaymentCompletedSuccessfullyEvent $event): void
    {
        try {
            if (!$event->hasSubscriptionData || !$event->subscriptionData) {
                return;
            }

            $subscriptionData = $event->subscriptionData;
            $selectedPlan = $subscriptionData['selected_plan'] ?? null;

            if (!$selectedPlan) {
                $this->logger->error('Subscription data missing selected plan', [
                    'transaction_id' => $event->transactionId,
                    'subscription_data_keys' => array_keys($subscriptionData)
                ]);
                return;
            }

            $createSubscriptionCommand = new CreateSubscriptionCommand(
                planId: $selectedPlan->getPlanId()->getValue(),
                customerEmail: $subscriptionData['customer_email'],
                firstName: $subscriptionData['billing_first_name'],
                lastName: $subscriptionData['billing_last_name'],
                street1: $subscriptionData['billing_address1'],
                street2: $subscriptionData['billing_address2'],
                city: $subscriptionData['billing_city'],
                state: $subscriptionData['billing_state'],
                postalCode: $subscriptionData['billing_postal'],
                country: $subscriptionData['billing_country'],
                phone: $subscriptionData['billing_phone'],
                startDate: new \DateTimeImmutable('+1 day'), // Start tomorrow
                customerVaultId: null // Let handler create one using legacy flow
            );

            $result = $this->createSubscriptionHandler->handle($createSubscriptionCommand);

            // Link the initial payment transaction to the newly created subscription
            if ($result->subscriptionId) {
                $this->transactionLinkingService->linkTransactionToSubscription(
                    $event->transactionId,
                    $result->subscriptionId
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to create subscription from payment event', [
                'transaction_id' => $event->transactionId,
                'error' => $e->getMessage(),
                'customer_email' => $event->customerEmail
            ]);
            
            // I will not rethrow - I don't want to break the payment flow
            // The payment was successful, subscription creation failure is a separate concern
        }
    }
}
