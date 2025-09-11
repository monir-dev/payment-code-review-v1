<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ProcessRefundCommand;
use App\Application\Response\ProcessRefundCommandResponse;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Payment\Event\PaymentRefundedSuccessfullyEvent;
use App\Domain\Payment\Service\RefundProcessingService;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Infrastructure\Event\DomainEventBus;
use App\Infrastructure\Event\PaymentRefundedEventListener;
use App\Repository\PaymentTransactionRepository;
use Psr\Log\LoggerInterface;

final class ProcessRefundCommandHandler
{
    private bool $listenerRegistered = false;

    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly DomainEventBus $eventBus,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly CancelSubscriptionCommandHandler $cancelSubscriptionHandler,
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly RefundProcessingService $refundProcessingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(ProcessRefundCommand $command): ProcessRefundCommandResponse
    {
        try {
            // Register event listener if not already registered (to avoid circular dependencies)
            $this->ensureEventListenerRegistered();

            $this->logger->info('Processing refund request', [
                'original_transaction_id' => $command->transactionId,
                'refund_amount' => $command->refundAmount,
            ]);

            // Process the refund through payment gateway
            $result = $this->paymentGateway->processRefund(
                $command->transactionId,
                $command->refundAmount
            );

            if ($result->isSuccessful()) {
                $this->logger->info('Refund processed successfully', [
                    'original_transaction_id' => $command->transactionId,
                    'refund_transaction_id' => $result->transactionId ?? 'N/A',
                    'refund_amount' => $command->refundAmount->format(),
                ]);

                // Fire domain event for successful refund
                $event = new PaymentRefundedSuccessfullyEvent(
                    originalTransactionId: $command->transactionId,
                    refundTransactionId: $result->transactionId ?? '',
                    refundAmount: $command->refundAmount->getAmount(),
                    currency: $command->refundAmount->getCurrency()->getCode()
                );

                $this->eventBus->publish($event);

            } else {
                $this->logger->error('Refund processing failed', [
                    'original_transaction_id' => $command->transactionId,
                    'refund_amount' => $command->refundAmount->format(),
                    'error_message' => $result->message ?? 'Unknown error',
                ]);
            }

            return new ProcessRefundCommandResponse(
                status: $result->status,
                transactionId: $result->transactionId ?? '',
                refundAmount: $command->refundAmount->getAmount(),
                originalTransactionId: $command->transactionId,
                message: $result->message ?? null
            );
        } catch (\Exception $e) {
            $this->logger->error('Refund processing exception', [
                'original_transaction_id' => $command->transactionId,
                'error' => $e->getMessage(),
            ]);

            return new ProcessRefundCommandResponse(
                status: 'error',
                transactionId: '',
                refundAmount: $command->refundAmount->getAmount(),
                originalTransactionId: $command->transactionId,
                message: 'Failed to process refund: ' . $e->getMessage()
            );
        }
    }

    private function ensureEventListenerRegistered(): void
    {
        if (!$this->listenerRegistered) {
            $listener = new PaymentRefundedEventListener(
                $this->subscriptionRepository,
                $this->cancelSubscriptionHandler,
                $this->transactionRepository,
                $this->refundProcessingService,
                $this->logger
            );

            $this->eventBus->subscribe(
                PaymentRefundedSuccessfullyEvent::class,
                [$listener, 'handle']
            );

            $this->listenerRegistered = true;
        }
    }
}
