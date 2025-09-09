<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Shared\ValueObject\BillingInformation;
use App\Dto\CreatePlanDto;

interface PaymentGatewayInterface
{
    /**
     * @return array{status: string, reason?: string, transaction_id?: string, decline_message?: string}
     */
    public function processPayment(
        string $transactionId,
        float $amount,
        string $currency,
        BillingInformation $billingInformation,
        ?string $gatewayToken = null
    ): array;

    /**
     * @return array{status: string, transaction_id?: string, message?: string}
     */
    public function processRefund(string $originalTransactionId, float $refundAmount): array;

    /**
     * @return array{status: string, form_url?: string, message?: string}
     */
    public function initializePayment(
        float $amount,
        string $currency,
        string $redirectUrl,
        BillingInformation $billingInformation
    ): array;

    /**
     * @return array{status: string, plan_id?: string, message?: string}
     */
    public function createPlan(CreatePlanDto $planDto): array;
}
