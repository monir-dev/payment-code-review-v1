<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Application\Command\CancelSubscriptionCommand;
use App\Application\Handler\CancelSubscriptionCommandHandler;
use App\Domain\Payment\Event\PaymentRefundedSuccessfullyEvent;
use App\Domain\Payment\Exception\InvalidRefundAmountException;
use App\Domain\Payment\Service\RefundProcessingService;
use App\Domain\Subscription\Entity\Subscription;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use App\Entity\PaymentTransaction;
use App\Repository\PaymentTransactionRepository;
use Exception;
use Psr\Log\LoggerInterface;

final class PaymentRefundedEventListener
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly CancelSubscriptionCommandHandler $cancelSubscriptionHandler,
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly RefundProcessingService $refundProcessingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(PaymentRefundedSuccessfullyEvent $event): void
    {
        try {
            $transaction = $this->transactionRepository->findByTransactionId($event->originalTransactionId);
            if (!$transaction) {
                $this->logger->warning('Transaction not found for refund processing', [
                    'original_transaction_id' => $event->originalTransactionId
                ]);
                return;
            }

            // Use domain service to process refund logic
            $result = $this->refundProcessingService->processRefund($transaction, $event);

            // Update transaction status based on domain logic
            $this->transactionRepository->updateTransactionStatus(
                $event->originalTransactionId, 
                $result->newStatus
            );
            
            $this->logger->info('Refund processed successfully', [
                'original_transaction_id' => $event->originalTransactionId,
                'refund_amount' => $result->refundAmount,
                'original_amount' => $result->originalAmount,
                'new_status' => $result->newStatus->getValue(),
                'is_partial_refund' => $result->isPartialRefund,
                'will_cancel_subscription' => $result->shouldCancelSubscription
            ]);

            // Cancel subscription if domain logic dictates
            if ($result->shouldCancelSubscription) {
                $subscription = $this->findSubscription($transaction);
                if ($subscription) {
                    $this->cancelSubscription($subscription, $event, $result);
                }
            }
            
        } catch (InvalidRefundAmountException $e) {
            $this->logger->error('Invalid refund amount', [
                'original_transaction_id' => $event->originalTransactionId,
                'refund_amount' => $event->refundAmount,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw domain exceptions
            
        } catch (Exception $e) {
            $this->logger->error('Failed to process refund event', [
                'original_transaction_id' => $event->originalTransactionId,
                'refund_amount' => $event->refundAmount,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException(
                'Failed to process refund: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function findSubscription(PaymentTransaction $transaction): ?Subscription
    {
        if ($transaction->getSubscriptionId() === null) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromString($transaction->getSubscriptionId());
        return $this->subscriptionRepository->findBySubscriptionId($subscriptionId);
    }

    private function cancelSubscription(
        Subscription $subscription, 
        PaymentRefundedSuccessfullyEvent $event,
        \App\Domain\Payment\Service\RefundProcessingResult $result
    ): void {
        try {
            $refundType = $result->isPartialRefund ? 'partial' : 'full';
            
            $cancelCommand = new CancelSubscriptionCommand(
                subscriptionId: $subscription->getSubscriptionId()->getValue(),
                reason: sprintf(
                    'Automatic cancellation due to %s refund of transaction %s (refund: $%.2f of $%.2f)',
                    $refundType,
                    $event->originalTransactionId,
                    $result->refundAmount,
                    $result->originalAmount
                ),
                cancelWithGateway: true // Cancel with payment gateway as well
            );

            $this->cancelSubscriptionHandler->handle($cancelCommand);
            
            $this->logger->info('Subscription canceled due to refund', [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'original_transaction_id' => $event->originalTransactionId,
                'refund_type' => $refundType,
                'refund_amount' => $result->refundAmount,
                'original_amount' => $result->originalAmount
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to cancel subscription after refund', [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'original_transaction_id' => $event->originalTransactionId,
                'refund_type' => $result->isPartialRefund ? 'partial' : 'full',
                'error' => $e->getMessage()
            ]);
            
            // Don't re-throw here - refund was successful, subscription cancellation is secondary
        }
    }
}
