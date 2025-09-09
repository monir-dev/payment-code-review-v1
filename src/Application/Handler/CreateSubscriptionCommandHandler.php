<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateSubscriptionCommand;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Billing\Repository\PlanRepositoryInterface;
use App\Domain\Billing\ValueObject\PlanId;
use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Subscription\Entity\Subscription;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use DateTimeImmutable;
use InvalidArgumentException;

final class CreateSubscriptionCommandHandler
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly PlanRepositoryInterface $planRepository,
        private readonly PaymentGatewayInterface $paymentGateway
    ) {
    }

    public function handle(CreateSubscriptionCommand $command): array
    {
        // Find the plan
        $planId = PlanId::fromString($command->planId);
        $plan = $this->planRepository->findByPlanId($planId);
        
        if (!$plan) {
            throw new InvalidArgumentException('Plan not found: ' . $command->planId);
        }

        if (!$plan->isAvailableForSubscription()) {
            throw new InvalidArgumentException('Plan is not available for subscription: ' . $command->planId);
        }

        // Create value objects
        $email = Email::fromString($command->customerEmail);
        $address = Address::create(
            $command->street1,
            $command->street2,
            $command->city,
            $command->state,
            $command->postalCode,
            $command->country
        );
        $billingInformation = new BillingInformation(
            $command->firstName,
            $command->lastName,
            $email,
            $address,
            $command->phone
        );

        // Get or create customer vault ID following legacy procedure
        $customerVaultId = $command->customerVaultId;
        
        if (!$customerVaultId) {
            // Create customer vault via NMI gateway (following legacy pattern)
            $billingInfo = [
                'first_name' => $command->firstName,
                'last_name' => $command->lastName,
                'address1' => $command->street1,
                'address2' => $command->street2 ?? '',
                'city' => $command->city,
                'state' => $command->state,
                'postal' => $command->postalCode,
                'country' => $command->country,
                'phone' => $command->phone ?? '',
            ];
            
            $vaultResult = $this->paymentGateway->createCustomerVault($billingInfo);
            
            if ($vaultResult['status'] !== 'success') {
                throw new InvalidArgumentException(
                    'Failed to create customer vault: ' . ($vaultResult['message'] ?? 'Unknown error')
                );
            }
            
            $customerVaultId = $vaultResult['customer_vault_id'];
        }

        // Create subscription with NMI API
        $startDate = $command->startDate ?? new DateTimeImmutable();
        
        $billingInfo = [
            'email' => $command->customerEmail,
            'first_name' => $command->firstName,
            'last_name' => $command->lastName,
            'address1' => $command->street1,
            'address2' => $command->street2 ?? '',
            'city' => $command->city,
            'state' => $command->state,
            'postal' => $command->postalCode,
            'country' => $command->country,
            'phone' => $command->phone ?? '',
        ];

        $nmiResult = $this->paymentGateway->createSubscription(
            $plan->getPlanId()->getValue(),
            $customerVaultId,
            $startDate,
            $billingInfo
        );

        if ($nmiResult['status'] !== 'success') {
            throw new InvalidArgumentException(
                'Failed to create subscription with NMI: ' . ($nmiResult['message'] ?? 'Unknown error')
            );
        }

        $nmiSubscriptionId = $nmiResult['subscription_id'];
        if (empty($nmiSubscriptionId)) {
            throw new InvalidArgumentException('NMI subscription ID not returned from gateway');
        }

        // Create subscription aggregate with NMI subscription ID
        $subscriptionId = SubscriptionId::fromString($nmiSubscriptionId);
        $originalTransactionId = $command->originalTransactionId 
            ? TransactionId::fromString($command->originalTransactionId)
            : null;

        $subscription = Subscription::create(
            $subscriptionId,
            $plan,
            $billingInformation,
            $startDate,
            $customerVaultId,
            $originalTransactionId
        );

        // Save subscription
        $this->subscriptionRepository->save($subscription);

        // Return result
        return [
            'subscription_id' => $subscription->getSubscriptionId()->getValue(),
            'customer_vault_id' => $customerVaultId,
            'nmi_transaction_id' => $nmiResult['transaction_id'] ?? null,
            'plan_name' => $subscription->getPlan()->getName(),
            'amount' => $subscription->getAmount()->format(),
            'start_date' => $subscription->getStartDate()->format('Y-m-d'),
            'next_charge_date' => $subscription->getNextChargeDate()->format('Y-m-d'),
            'status' => $subscription->getStatus()->getValue(),
            'message' => 'Subscription created successfully with NMI',
            'events' => $subscription->getUncommittedEvents()
        ];
    }
}
