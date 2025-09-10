<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CancelSubscriptionCommand;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class CancelSubscriptionCommandHandler
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(CancelSubscriptionCommand $command): array
    {
        try {
            // 1. Validate and fetch the subscription using domain value object
            $subscriptionId = SubscriptionId::fromString($command->subscriptionId);
            $subscription = $this->subscriptionRepository->findBySubscriptionId($subscriptionId);

            if (!$subscription) {
                throw new InvalidArgumentException(
                    'Subscription not found with ID: ' . $command->subscriptionId
                );
            }

            $this->logger->info('Starting subscription cancellation process', [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'current_status' => $subscription->getStatus()->getValue(),
                'reason' => $command->reason,
                'cancelled_by' => $command->cancelledBy
            ]);

            // 2. Cancel with payment gateway first (if requested)
            $gatewayResult = null;
            if ($command->cancelWithGateway && $subscription->getCustomerVaultId()) {
                $gatewayResult = $this->paymentGateway->cancelSubscription(
                    $subscription->getSubscriptionId()->getValue()
                );

                // If gateway cancellation fails, we might still want to proceed depending on business rules
                if ($gatewayResult['status'] === 'error') {
                    $this->logger->warning('Gateway cancellation failed but continuing with local cancellation', [
                        'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                        'gateway_error' => $gatewayResult['message'] ?? 'Unknown error'
                    ]);
                }
            }

            // 3. Apply domain business rules and cancel the subscription
            // This will validate business rules and emit domain events
            $subscription->cancel($command->reason);

            // 4. Persist the changes using DDD repository
            $this->subscriptionRepository->save($subscription);

            // 5. Prepare success response
            $result = [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'status' => $subscription->getStatus()->getValue(),
                'reason' => $command->reason,
                'cancelled_at' => (new \DateTimeImmutable())->format('c'),
                'gateway_cancelled' => $command->cancelWithGateway,
                'events' => [] // Domain events will be handled by event listeners
            ];

            if ($gatewayResult) {
                $result['gateway_result'] = [
                    'status' => $gatewayResult['status'],
                    'message' => $gatewayResult['message'] ?? '',
                    'already_cancelled' => $gatewayResult['already_cancelled'] ?? false
                ];
            }

            $this->logger->info('Subscription cancelled successfully', [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'reason' => $command->reason,
                'gateway_status' => $gatewayResult['status'] ?? 'not_attempted',
                'cancelled_by' => $command->cancelledBy
            ]);

            return $result;

        } catch (DomainException $e) {
            // Domain validation failed (e.g., subscription already cancelled)
            $this->logger->warning('Domain cancellation validation failed', [
                'subscription_id' => $command->subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw new InvalidArgumentException(
                'Cannot cancel subscription: ' . $e->getMessage(),
                0,
                $e
            );

        } catch (InvalidArgumentException $e) {
            $this->logger->error('Subscription cancellation validation error', [
                'subscription_id' => $command->subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during subscription cancellation', [
                'subscription_id' => $command->subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException(
                'Failed to cancel subscription due to unexpected error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
