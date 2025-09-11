<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\RebillSubscriptionCommand;
use App\Application\Response\RebillSubscriptionCommandResponse;
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

    public function handle(RebillSubscriptionCommand $command): RebillSubscriptionCommandResponse
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
                    $rebillAmount
                );

                // Check if gateway processing failed
                if ($gatewayResult->isFailed()) {
                    throw new \RuntimeException(
                        'Gateway rebill failed: ' . ($gatewayResult->message ?? 'Unknown error')
                    );
                }
            }

            // 4. Apply domain business rules and process the rebill
            $transactionId = $gatewayResult->transactionId ?? 'manual-' . uniqid();
            $subscription->processRebill($rebillAmount, $transactionId, $command->reason);

            // 5. Persist the changes using DDD repository
            $this->subscriptionRepository->save($subscription);

            // 6. Prepare success response
            $gatewayResultArray = null;
            if ($gatewayResult) {
                $gatewayResultArray = [
                    'status' => $gatewayResult->status,
                    'message' => $gatewayResult->message ?? '',
                    'gateway_amount' => $gatewayResult->amount?->getAmount() ?? null
                ];
            }

            $this->logger->info('Subscription rebilled successfully', [
                'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                'transaction_id' => $transactionId,
                'amount' => $rebillAmount->format(),
                'next_charge_date' => $subscription->getNextChargeDate()->format('Y-m-d'),
                'reason' => $command->reason,
                'gateway_status' => $gatewayResult->status ?? 'not_attempted',
                'initiated_by' => $command->initiatedBy
            ]);

            return new RebillSubscriptionCommandResponse(
                subscriptionId: $subscription->getSubscriptionId()->getValue(),
                status: $subscription->getStatus()->getValue(),
                transactionId: $transactionId,
                amount: $rebillAmount->format(),
                reason: $command->reason,
                nextChargeDate: $subscription->getNextChargeDate()->format('Y-m-d'),
                rebilledAt: (new \DateTimeImmutable())->format('c'),
                gatewayProcessed: $command->processWithGateway,
                events: [], // Domain events will be handled by event listeners
                gatewayResult: $gatewayResultArray
            );

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
            $this->logger->error('Subscription rebill validation error', [
                'subscription_id' => $command->subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw $e;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during subscription rebill', [
                'subscription_id' => $command->subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw new \RuntimeException(
                'Failed to rebill subscription due to unexpected error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
