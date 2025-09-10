<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Application\Command\CancelSubscriptionCommand;
use App\Application\Handler\CancelSubscriptionCommandHandler;
use App\Domain\Payment\Event\PaymentRefundedSuccessfullyEvent;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use App\Repository\PaymentTransactionRepository;
use Exception;
use Psr\Log\LoggerInterface;

final class PaymentRefundedEventListener
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly CancelSubscriptionCommandHandler $cancelSubscriptionHandler,
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(PaymentRefundedSuccessfullyEvent $event): void
    {
        try {
            // Find the transaction by transaction_id to get the linked subscription_id
            $transaction = $this->transactionRepository->findByTransactionId($event->originalTransactionId);

            if (!$transaction || !$transaction->getSubscriptionId()) {
                return;
            }

            // Find the subscription by subscription_id
            $subscriptionId = SubscriptionId::fromString($transaction->getSubscriptionId());
            $subscription = $this->subscriptionRepository->findBySubscriptionId($subscriptionId);

            if (!$subscription) {
                return;
            }

            // Cancel the subscription
            try {
                $cancelCommand = new CancelSubscriptionCommand(
                    subscriptionId: $subscription->getSubscriptionId()->getValue(),
                    reason: sprintf(
                        'Automatic cancellation due to refund of transaction %s (refund amount: $%.2f)',
                        $event->originalTransactionId,
                        $event->refundAmount
                    ),
                    cancelWithGateway: true // Cancel with payment gateway as well
                );

                $this->cancelSubscriptionHandler->handle($cancelCommand);

            } catch (Exception $e) {
                $this->logger->error('Failed to cancel subscription after refund', [
                    'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                    'original_transaction_id' => $event->originalTransactionId,
                    'error' => $e->getMessage()
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('Failed to process refund event for subscription cancellation', [
                'original_transaction_id' => $event->originalTransactionId,
                'error' => $e->getMessage()
            ]);
        }
    }
}