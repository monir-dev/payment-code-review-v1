<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\InitializePaymentCommand;
use App\Application\Response\InitializePaymentCommandResponse;
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

    public function handle(InitializePaymentCommand $command): InitializePaymentCommandResponse
    {
        try {
            // Create BillingInformation value object
            $email = Email::fromString($command->billingEmail);
            $address = Address::create(
                street1: $command->billingAddress1,
                street2: $command->billingAddress2,
                city: $command->billingCity,
                state: $command->billingState,
                postalCode: $command->billingPostal,
                country: $command->billingCountry
            );
            $billingInformation = new BillingInformation(
                firstName: $command->billingFirstName,
                lastName: $command->billingLastName,
                email: $email,
                address: $address,
                phone: $command->billingPhone
            );

            // Initialize payment through gateway
            $result = $this->paymentGateway->initializePayment(
                $command->amount,
                $command->amount->getCurrency()->getCode(),
                $command->redirectUrl,
                $billingInformation
            );

            if ($result->isSuccessful()) {
                $this->logger->info('Payment initialization successful', [
                    'amount' => $command->amount->format(),
                    'currency' => $command->currency,
                    'is_subscription' => $command->isSubscription,
                    'plan_id' => $command->planId,
                    'customer_email' => $command->billingEmail
                ]);
            } else {
                $this->logger->error('Payment initialization failed', [
                    'message' => $result->message ?? 'Unknown error',
                    'amount' => $command->amount->format(),
                    'customer_email' => $command->billingEmail
                ]);
            }

            return new InitializePaymentCommandResponse(
                status: $result->status,
                redirectUrl: $result->formUrl ?? null,
                tokenId: $result->tokenId ?? null,
                message: $result->message ?? null,
                gatewayResponse: $result->rawResponse ?? []
            );
        } catch (\Exception $e) {
            $this->logger->error('Payment initialization exception', [
                'error' => $e->getMessage(),
                'amount' => $command->amount->format(),
                'customer_email' => $command->billingEmail
            ]);

            return new InitializePaymentCommandResponse(
                status: 'error',
                message: 'Failed to initialize payment: ' . $e->getMessage()
            );
        }
    }
}
