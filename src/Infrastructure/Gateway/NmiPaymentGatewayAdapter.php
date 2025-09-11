<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateway;

use App\Application\Response\Gateway\CancelSubscriptionResponse;
use App\Application\Response\Gateway\CompleteTransactionResponse;
use App\Application\Response\Gateway\CreateCustomerVaultResponse;
use App\Application\Response\Gateway\CreatePlanResponse;
use App\Application\Response\Gateway\CreateSubscriptionResponse;
use App\Application\Response\Gateway\InitializePaymentResponse;
use App\Application\Response\Gateway\ProcessPaymentResponse;
use App\Application\Response\Gateway\RebillResponse;
use App\Application\Response\Gateway\RefundResponse;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Money;
use App\Service\NmiPaymentGateway;
use App\Domain\Billing\Dto\CreatePlanDto;
use Exception;
use Psr\Log\LoggerInterface;

final class NmiPaymentGatewayAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly NmiPaymentGateway $nmiGateway,
        private readonly LoggerInterface $logger
    ) {
    }

    public function processPayment(
        string $transactionId,
        Money $amount,
        string $currency,
        BillingInformation $billingInformation,
        ?string $gatewayToken = null
    ): ProcessPaymentResponse {
        try {
            if ($gatewayToken) {
                // Complete an existing payment with token
                $result = $this->nmiGateway->completeTransaction($gatewayToken);

                return new ProcessPaymentResponse(
                    status: $result->isSuccessful() ? 'approved' : 'declined',
                    transactionId: $result->transactionId ?? '',
                    reason: $result->declineMessage ?? $result->errorMessage ?? ''
                );
            } else {
                // Initialize new payment
                $result = $this->nmiGateway->initializePayment(
                    $amount,
                    $currency,
                    '', // redirectUrl handled at controller level
                    $billingInformation
                );

                return new ProcessPaymentResponse(
                    status: $result->isSuccessful() ? 'approved' : 'declined',
                    reason: $result->message ?? ''
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Payment gateway error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return new ProcessPaymentResponse(
                status: 'failed',
                reason: 'Payment processing failed'
            );
        }
    }

    public function processRefund(string $originalTransactionId, Money $refundAmount): RefundResponse
    {
        try {
            $result = $this->nmiGateway->processRefund($originalTransactionId, $refundAmount);

            return new RefundResponse(
                status: $result->status,
                transactionId: $result->transactionId ?? '',
                refundAmount: $refundAmount->getAmount(),
                originalTransactionId: $originalTransactionId,
                message: $result->message ?? ''
            );
        } catch (Exception $e) {
            $this->logger->error('Refund gateway error', [
                'original_transaction_id' => $originalTransactionId,
                'error' => $e->getMessage()
            ]);

            return new RefundResponse(
                status: 'error',
                refundAmount: $refundAmount->getAmount(),
                originalTransactionId: $originalTransactionId,
                message: 'Refund processing failed'
            );
        }
    }

    public function initializePayment(
        Money $amount,
        string $currency,
        string $redirectUrl,
        BillingInformation $billingInformation,
        array $shippingInfo = []
    ): InitializePaymentResponse {
        try {
            $result = $this->nmiGateway->initializePayment($amount, $currency, $redirectUrl, $billingInformation, $shippingInfo);

            return new InitializePaymentResponse(
                status: $result->status,
                formUrl: $result->formUrl ?? '',
                message: $result->message ?? ''
            );
        } catch (Exception $e) {
            $this->logger->error('Payment initialization error', [
                'error' => $e->getMessage()
            ]);

            return new InitializePaymentResponse(
                status: 'error',
                message: 'Payment initialization failed'
            );
        }
    }

    public function completeTransactionByTokenId(string $tokenId, ?string $subscriptionId = null): CompleteTransactionResponse
    {
        try {
            return $this->nmiGateway->completeTransactionByTokenId($tokenId, $subscriptionId);
        } catch (Exception $e) {
            $this->logger->error('Transaction completion gateway error', [
                'token_id' => $tokenId,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return new CompleteTransactionResponse(
                status: 'error',
                transactionId: '',
                errorMessage: 'Transaction completion failed: ' . $e->getMessage()
            );
        }
    }

    public function createPlan(CreatePlanDto $planDto): CreatePlanResponse
    {
        try {
            return $this->nmiGateway->createPlan($planDto);
        } catch (Exception $e) {
            $this->logger->error('Plan creation gateway error', [
                'plan_name' => $planDto->planName,
                'plan_id' => $planDto->planId,
                'error' => $e->getMessage()
            ]);

            return new CreatePlanResponse(
                status: 'error',
                message: 'Plan creation failed: ' . $e->getMessage()
            );
        }
    }

    public function createCustomerVault(array $billingInfo): CreateCustomerVaultResponse
    {
        try {
            return $this->nmiGateway->createCustomerVault($billingInfo);
        } catch (Exception $e) {
            $this->logger->error('Customer vault creation gateway error', [
                'billing_info' => $billingInfo,
                'error' => $e->getMessage()
            ]);

            return new CreateCustomerVaultResponse(
                status: 'error',
                message: 'Customer vault creation failed: ' . $e->getMessage()
            );
        }
    }

    public function cancelSubscription(string $subscriptionId): CancelSubscriptionResponse
    {
        try {
            return $this->nmiGateway->cancelSubscription($subscriptionId);
        } catch (Exception $e) {
            $this->logger->error('Subscription cancellation gateway error', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return new CancelSubscriptionResponse(
                status: 'error',
                message: 'Subscription cancellation failed: ' . $e->getMessage()
            );
        }
    }

    public function processRebilling(string $subscriptionId, string $customerVaultId, ?Money $amount = null): RebillResponse
    {
        try {
            $result = $this->nmiGateway->processRebilling($subscriptionId, $customerVaultId, $amount);

            return new RebillResponse(
                status: $result->status,
                transactionId: $result->transactionId ?? '',
                amount: $result->amount ?: $amount,
                subscriptionId: $subscriptionId,
                message: $result->message ?? ''
            );
        } catch (Exception $e) {
            $this->logger->error('Rebilling gateway error', [
                'subscription_id' => $subscriptionId,
                'customer_vault_id' => $customerVaultId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return new RebillResponse(
                status: 'error',
                subscriptionId: $subscriptionId,
                message: 'Rebilling failed: ' . $e->getMessage()
            );
        }
    }

    public function createSubscription(
        string $planId,
        string $customerVaultId,
        \DateTimeImmutable $startDate,
        array $billingInfo = []
    ): CreateSubscriptionResponse {
        try {
            return $this->nmiGateway->createSubscription($planId, $customerVaultId, $startDate, $billingInfo);
        } catch (Exception $e) {
            $this->logger->error('Subscription creation gateway error', [
                'plan_id' => $planId,
                'customer_vault_id' => $customerVaultId,
                'start_date' => $startDate->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);

            return new CreateSubscriptionResponse(
                status: 'error',
                message: 'Subscription creation failed: ' . $e->getMessage()
            );
        }
    }

}
