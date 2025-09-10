<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CompletePaymentCommand;
use App\Domain\Payment\Event\PaymentCompletedSuccessfullyEvent;
use App\Infrastructure\Event\DomainEventBus;
use App\Infrastructure\Event\PaymentCompletedEventListener;
use App\Service\NmiPaymentGateway;
use Psr\Log\LoggerInterface;

final class CompletePaymentCommandHandler
{
    private bool $listenerRegistered = false;

    public function __construct(
        private readonly NmiPaymentGateway $paymentGateway,
        private readonly DomainEventBus $eventBus,
        private readonly CreateSubscriptionCommandHandler $createSubscriptionHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(CompletePaymentCommand $command): array
    {
        try {
            // Register event listener if not already registered (to avoid circular dependencies)
            $this->ensureEventListenerRegistered();

            // Complete the transaction through NMI
            $result = $this->paymentGateway->completeTransactionByTokenId($command->tokenId);

            if ($result['status'] === 'success') {
                $this->logger->info('Payment completed successfully', [
                    'transaction_id' => $result['transaction_id'],
                ]);

                // Fire domain event for successful payment using data from command
                $event = new PaymentCompletedSuccessfullyEvent(
                    transactionId: $result['transaction_id'],
                    amount: $command->paymentAmount ?? 0.0,
                    currency: 'USD', // Default currency, could be made configurable
                    customerEmail: $command->subscriptionData['customer_email'] ?? '',
                    billingInformation: $this->extractBillingInformation($command->subscriptionData),
                    hasSubscriptionData: $command->subscriptionData !== null,
                    subscriptionData: $command->subscriptionData
                );

                $this->eventBus->publish($event);

            } elseif ($result['status'] === 'declined') {
                $this->logger->warning('Payment declined', [
                    'decline_message' => $result['decline_message'] ?? 'Payment declined'
                ]);
            } else {
                $this->logger->error('Payment failed', [
                    'error_message' => $result['error_message'] ?? 'Payment failed'
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Payment completion exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error_message' => 'Failed to complete payment: ' . $e->getMessage()
            ];
        }
    }

    private function extractBillingInformation(?array $subscriptionData): array
    {
        if (!$subscriptionData) {
            return [];
        }

        return [
            'first_name' => $subscriptionData['billing_first_name'] ?? '',
            'last_name' => $subscriptionData['billing_last_name'] ?? '',
            'email' => $subscriptionData['customer_email'] ?? '',
            'address1' => $subscriptionData['billing_address1'] ?? '',
            'address2' => $subscriptionData['billing_address2'] ?? '',
            'city' => $subscriptionData['billing_city'] ?? '',
            'state' => $subscriptionData['billing_state'] ?? '',
            'postal_code' => $subscriptionData['billing_postal'] ?? '',
            'country' => $subscriptionData['billing_country'] ?? '',
            'phone' => $subscriptionData['billing_phone'] ?? '',
        ];
    }

    private function ensureEventListenerRegistered(): void
    {
        if (!$this->listenerRegistered) {
            // Create PaymentCompletedEventListener and register it manually
            $listener = new PaymentCompletedEventListener(
                $this->createSubscriptionHandler,
                $this->logger
            );

            $this->eventBus->subscribe(
                PaymentCompletedSuccessfullyEvent::class,
                [$listener, 'handle']
            );

            $this->listenerRegistered = true;
        }
    }
}
