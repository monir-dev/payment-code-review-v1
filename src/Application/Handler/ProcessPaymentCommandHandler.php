<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ProcessPaymentCommand;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Repository\PaymentRepositoryInterface;
use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Shared\ValueObject\Money;

final class ProcessPaymentCommandHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayInterface $paymentGateway
    ) {
    }

    public function handle(ProcessPaymentCommand $command): array
    {
        // Create value objects
        $money = Money::fromFloat($command->amount, $command->currencyCode);
        $email = Email::fromString($command->email);
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

        // Create payment aggregate
        $transactionId = TransactionId::generate();
        $payment = Payment::create(
            $transactionId,
            $money,
            $billingInformation,
            $command->gatewayToken
        );

        $gatewayResult = $this->paymentGateway->processPayment(
            $transactionId->getValue(),
            $money->getAmount(),
            $money->getCurrency()->getCode(),
            $billingInformation
        );

        // Update payment status based on gateway result
        if ($gatewayResult['status'] === 'approved') {
            $payment->approve();
        } elseif ($gatewayResult['status'] === 'declined') {
            $payment->decline($gatewayResult['reason'] ?? 'Payment declined');
        }

        $this->paymentRepository->save($payment);

        return [
            'status' => $payment->getStatus()->getValue(),
            'transaction_id' => $payment->getTransactionId()->getValue(),
            'amount' => $payment->getAmount()->format(),
            'events' => $payment->getUncommittedEvents()
        ];
    }
}
