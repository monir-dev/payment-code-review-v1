<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\RebillSubscriptionCommand;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class RebillSubscriptionCommandHandler
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(RebillSubscriptionCommand $command): array
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

            // 2. Determine the amount to rebill
            $planAmount = $subscription->getPlan()->getAmount();
            $rebillAmount = $command->customAmount 
                ? Money::fromFloat($command->customAmount, $planAmount->getCurrency()->getCode())
                : $planAmount;

            $this->logger->info('Starting subscription rebill process', [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'current_status' => $subscription->getStatus()->getValue(),
                'plan_amount' => $planAmount->format(),
                'rebill_amount' => $rebillAmount->format(),
                'reason' => $command->reason,
                'initiated_by' => $command->initiatedBy
            ]);

            // 3. Process rebill with payment gateway (if requested)
            $gatewayResult = null;
            if ($command->processWithGateway) {
                if (!$subscription->getCustomerVaultId()) {
                    throw new InvalidArgumentException(
                        'Cannot process rebill with gateway: subscription has no customer vault ID'
                    );
                }

                $gatewayResult = $this->paymentGateway->processRebilling(
                    $subscription->getSubscriptionId()->getValue(),
                    $subscription->getCustomerVaultId(),
                    $rebillAmount->getAmount()
                );

                // Check if gateway processing failed
                if ($gatewayResult['status'] === 'error' || $gatewayResult['status'] === 'declined') {
                    throw new \RuntimeException(
                        'Gateway rebill failed: ' . ($gatewayResult['message'] ?? 'Unknown error')
                    );
                }
            }

            // 4. Apply domain business rules and process the rebill
            $transactionId = $gatewayResult['transaction_id'] ?? 'manual-' . uniqid();
            $subscription->processRebill($rebillAmount, $transactionId, $command->reason);

            // 5. Persist the changes using DDD repository
            $this->subscriptionRepository->save($subscription);

            // 6. Prepare success response
            $result = [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'status' => $subscription->getStatus()->getValue(),
                'transaction_id' => $transactionId,
                'amount' => $rebillAmount->format(),
                'reason' => $command->reason,
                'next_charge_date' => $subscription->getNextChargeDate()->format('Y-m-d'),
                'rebilled_at' => (new \DateTimeImmutable())->format('c'),
                'gateway_processed' => $command->processWithGateway,
                'events' => [] // Domain events will be handled by event listeners
            ];

            if ($gatewayResult) {
                $result['gateway_result'] = [
                    'status' => $gatewayResult['status'],
                    'message' => $gatewayResult['message'] ?? '',
                    'gateway_amount' => $gatewayResult['amount'] ?? null
                ];
            }

            $this->logger->info('Subscription rebilled successfully', [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'transaction_id' => $transactionId,
                'amount' => $rebillAmount->format(),
                'next_charge_date' => $subscription->getNextChargeDate()->format('Y-m-d'),
                'reason' => $command->reason,
                'gateway_status' => $gatewayResult['status'] ?? 'not_attempted',
                'initiated_by' => $command->initiatedBy
            ]);

            return $result;

        } catch (DomainException $e) {
            // Domain validation failed (e.g., subscription not active)
            $this->logger->warning('Domain rebill validation failed', [
                'subscription_id' => $command->subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw new InvalidArgumentException(
                'Cannot rebill subscription: ' . $e->getMessage(),
                0,
                $e
            );

        } catch (InvalidArgumentException $e) {
            // Re-throw validation errors as-is
            $this->logger->error('Subscription rebill validation error', [
                'subscription_id' => $command->subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw $e;

        } catch (\Exception $e) {
            // Unexpected errors
            $this->logger->error('Unexpected error during subscription rebill', [
                'subscription_id' => $command->subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException(
                'Failed to rebill subscription due to unexpected error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
