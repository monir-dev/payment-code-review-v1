<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\InitializePaymentCommand;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Email;
use Psr\Log\LoggerInterface;

final class InitializePaymentCommandHandler
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(InitializePaymentCommand $command): array
    {
        try {
            // Create BillingInformation value object
            $email = Email::fromString($command->billingEmail);
            $address = Address::create(
                $command->billingAddress1,
                $command->billingAddress2,
                $command->billingCity,
                $command->billingState,
                $command->billingPostal,
                $command->billingCountry
            );
            $billingInformation = new BillingInformation(
                $command->billingFirstName,
                $command->billingLastName,
                $email,
                $address,
                $command->billingPhone
            );

            // Initialize payment through gateway
            $result = $this->paymentGateway->initializePayment(
                $command->amount,
                $command->currency,
                $command->redirectUrl,
                $billingInformation
            );

            if ($result['status'] === 'success') {
                $this->logger->info('Payment initialization successful', [
                    'amount' => $command->amount,
                    'currency' => $command->currency,
                    'is_subscription' => $command->isSubscription,
                    'plan_id' => $command->planId,
                    'customer_email' => $command->billingEmail
                ]);
            } else {
                $this->logger->error('Payment initialization failed', [
                    'message' => $result['message'] ?? 'Unknown error',
                    'amount' => $command->amount,
                    'customer_email' => $command->billingEmail
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Payment initialization exception', [
                'error' => $e->getMessage(),
                'amount' => $command->amount,
                'customer_email' => $command->billingEmail
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to initialize payment: ' . $e->getMessage()
            ];
        }
    }
}
