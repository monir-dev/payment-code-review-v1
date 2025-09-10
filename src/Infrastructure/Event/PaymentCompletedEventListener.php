<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Application\Command\CreateSubscriptionCommand;
use App\Application\Handler\CreateSubscriptionCommandHandler;
use App\Domain\Payment\Event\PaymentCompletedSuccessfullyEvent;
use Psr\Log\LoggerInterface;

final class PaymentCompletedEventListener
{
    public function __construct(
        private readonly CreateSubscriptionCommandHandler $createSubscriptionHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(PaymentCompletedSuccessfullyEvent $event): void
    {
        try {
            if (!$event->hasSubscriptionData || !$event->subscriptionData) {
                $this->logger->info('Payment completed without subscription data', [
                    'transaction_id' => $event->transactionId,
                    'amount' => $event->amount
                ]);
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
                originalTransactionId: $event->transactionId,
                customerVaultId: null // Let handler create one using legacy flow
            );

            $result = $this->createSubscriptionHandler->handle($createSubscriptionCommand);

            $this->logger->info('Subscription created successfully from payment event', [
                'transaction_id' => $event->transactionId,
                'subscription_id' => $result['subscription_id'] ?? 'N/A',
                'customer_vault_id' => $result['customer_vault_id'] ?? 'N/A',
                'plan_id' => $selectedPlan->getPlanId()->getValue(),
                'customer_email' => $subscriptionData['customer_email'],
                'event_driven' => true
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create subscription from payment event', [
                'transaction_id' => $event->transactionId,
                'error' => $e->getMessage(),
                'customer_email' => $event->customerEmail,
                'event_driven' => true
            ]);
            
            // I will not rethrow - I don't want to break the payment flow
            // The payment was successful, subscription creation failure is a separate concern
        }
    }
}
