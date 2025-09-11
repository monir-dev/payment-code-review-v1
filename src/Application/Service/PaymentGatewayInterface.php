<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Response\Gateway\CancelSubscriptionResponse;
use App\Application\Response\Gateway\CompleteTransactionResponse;
use App\Application\Response\Gateway\CreateCustomerVaultResponse;
use App\Application\Response\Gateway\CreatePlanResponse;
use App\Application\Response\Gateway\CreateSubscriptionResponse;
use App\Application\Response\Gateway\InitializePaymentResponse;
use App\Application\Response\Gateway\ProcessPaymentResponse;
use App\Application\Response\Gateway\RebillResponse;
use App\Application\Response\Gateway\RefundResponse;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Billing\Dto\CreatePlanDto;
use DateTimeImmutable;

interface PaymentGatewayInterface
{
    public function processPayment(
        string $transactionId,
        Money $amount,
        string $currency,
        BillingInformation $billingInformation,
        ?string $gatewayToken = null
    ): ProcessPaymentResponse;

    public function processRefund(string $originalTransactionId, Money $refundAmount): RefundResponse;

    public function initializePayment(
        Money $amount,
        string $currency,
        string $redirectUrl,
        BillingInformation $billingInformation,
        array $shippingInfo = []
    ): InitializePaymentResponse;

    public function completeTransactionByTokenId(string $tokenId, ?string $subscriptionId = null): CompleteTransactionResponse;

    public function createPlan(CreatePlanDto $planDto): CreatePlanResponse;

    /**
     * @param array<string, string> $billingInfo
     */
    public function createCustomerVault(array $billingInfo): CreateCustomerVaultResponse;

    public function cancelSubscription(string $subscriptionId): CancelSubscriptionResponse;

    public function processRebilling(string $subscriptionId, string $customerVaultId, ?Money $amount = null): RebillResponse;

    public function createSubscription(
        string $planId,
        string $customerVaultId,
        DateTimeImmutable $startDate,
        array $billingInfo = []
    ): CreateSubscriptionResponse;
}
