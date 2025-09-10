<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Application\Command\CancelSubscriptionCommand;
use App\Application\Handler\CancelSubscriptionCommandHandler;
use App\Domain\Payment\Event\PaymentRefundedSuccessfullyEvent;
use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use Exception;
use Psr\Log\LoggerInterface;

final class PaymentRefundedEventListener
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly CancelSubscriptionCommandHandler $cancelSubscriptionHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(PaymentRefundedSuccessfullyEvent $event): void
    {
        try {
            $this->logger->info('Processing payment refund event for subscription cancellation', [
                'original_transaction_id' => $event->originalTransactionId,
                'refund_transaction_id' => $event->refundTransactionId,
                'refund_amount' => $event->refundAmount,
                'event_driven' => true
            ]);

            $transactionId = TransactionId::fromString($event->originalTransactionId);
            $subscriptions = $this->subscriptionRepository->findByOriginalTransactionId($transactionId);

            if (empty($subscriptions)) {
                $this->logger->info('No subscriptions found for refunded transaction', [
                    'original_transaction_id' => $event->originalTransactionId,
                    'event_driven' => true
                ]);
                return;
            }

            $cancelledCount = 0;
            foreach ($subscriptions as $subscription) {
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
                    $cancelledCount++;

                    $this->logger->info('Subscription cancelled due to refund', [
                        'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                        'original_transaction_id' => $event->originalTransactionId,
                        'refund_transaction_id' => $event->refundTransactionId,
                        'event_driven' => true
                    ]);

                } catch (Exception $e) {
                    $this->logger->error('Failed to cancel subscription after refund', [
                        'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                        'original_transaction_id' => $event->originalTransactionId,
                        'error' => $e->getMessage(),
                        'event_driven' => true
                    ]);

                    continue;
                }
            }

            $this->logger->info('Completed subscription cancellations for refund', [
                'original_transaction_id' => $event->originalTransactionId,
                'subscriptions_found' => count($subscriptions),
                'subscriptions_cancelled' => $cancelledCount,
                'refund_amount' => $event->refundAmount,
                'event_driven' => true
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to process refund event for subscription cancellation', [
                'original_transaction_id' => $event->originalTransactionId,
                'error' => $e->getMessage(),
                'event_driven' => true
            ]);
        }
    }
}
