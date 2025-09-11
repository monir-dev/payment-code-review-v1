<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CompletePaymentCommand;
use App\Application\Response\CompletePaymentCommandResponse;
use App\Domain\Payment\Event\PaymentCompletedSuccessfullyEvent;
use App\Domain\Payment\Event\TransactionCompletedEvent;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Shared\ValueObject\Money;
use App\Infrastructure\Event\DomainEventBus;
use App\Infrastructure\Event\EventListenerRegistry;
use Psr\Log\LoggerInterface;

final class CompletePaymentCommandHandler
{
    private bool $listenerRegistered = false;

    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly DomainEventBus $eventBus,
        private readonly EventListenerRegistry $eventListenerRegistry,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(CompletePaymentCommand $command): CompletePaymentCommandResponse
    {
        try {
            // Register event listener if not already registered (to avoid circular dependencies)
            $this->ensureEventListenerRegistered();

            // Complete the transaction with NMI
            $gatewayResult = $this->paymentGateway->completeTransactionByTokenId($command->tokenId);

            if ($gatewayResult->isSuccessful()) {
                // Trigger TransactionCompletedEvent for database persistence
                $transactionEvent = TransactionCompletedEvent::fromNmiResponse([
                    'transaction-id' => $gatewayResult->transactionId,
                    'amount' => (string)($gatewayResult->amount?->getAmount() ?? 0.0),
                    'currency' => $gatewayResult->currency ?: 'USD',
                    'token-id' => $gatewayResult->tokenId ?? '',
                    'billing' => $gatewayResult->billingInfo ?? []
                ], null);

                $this->eventBus->publish($transactionEvent);

                // Trigger PaymentCompletedSuccessfullyEvent for subscription creation if applicable
                if ($command->subscriptionData) {
                    $billingInformation = $this->extractBillingInformation($command->subscriptionData);

                    $event = new PaymentCompletedSuccessfullyEvent(
                        transactionId: $gatewayResult->transactionId,
                        amount: $command->paymentAmount ?: ($gatewayResult->amount ?? Money::fromFloat(0.0, $gatewayResult->currency ?: 'USD')),
                        customerEmail: $billingInformation['email'] ?? '',
                        billingInformation: $billingInformation,
                        hasSubscriptionData: $command->subscriptionData !== null,
                        subscriptionData: $command->subscriptionData
                    );

                    $this->eventBus->publish($event);
                }

            } elseif ($gatewayResult->isDeclined()) {
                $this->logger->warning('Payment declined', [
                    'decline_message' => $gatewayResult->declineMessage ?? 'Payment declined'
                ]);

            } else {
                $this->logger->error('Payment failed', [
                    'error_message' => $gatewayResult->errorMessage ?? 'Payment failed'
                ]);
            }

            return new CompletePaymentCommandResponse(
                status: $gatewayResult->status,
                transactionId: $gatewayResult->transactionId,
                message: null, // Gateway doesn't provide general message
                declineMessage: $gatewayResult->declineMessage,
                errorMessage: $gatewayResult->errorMessage
            );
        } catch (\Exception $e) {
            $this->logger->error('Payment completion exception', [
                'error' => $e->getMessage()
            ]);

            return new CompletePaymentCommandResponse(
                status: 'error',
                transactionId: '',
                errorMessage: 'Failed to complete payment: ' . $e->getMessage()
            );
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
            // Force EventListenerRegistry instantiation by accessing it
            // The registry constructor registers all event listeners automatically
            get_class($this->eventListenerRegistry); // lazy initialization trick, to prevent circular dependency
            $this->listenerRegistered = true;
        }
    }
}
