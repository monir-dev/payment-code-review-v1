<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateway;

use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Service\NmiPaymentGateway;
use App\Dto\CreatePlanDto;
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
        float $amount,
        string $currency,
        BillingInformation $billingInformation,
        ?string $gatewayToken = null
    ): array {
        // Convert domain objects to NMI format
        $billingData = $this->convertBillingInformationToArray($billingInformation);
        
        try {
            if ($gatewayToken) {
                // Complete an existing payment with token
                $request = new \stdClass();
                $request->token_id = $gatewayToken;
                
                $result = $this->nmiGateway->completeTransaction($request);
                
                return [
                    'status' => $result['status'] === 'success' ? 'approved' : 'declined',
                    'transaction_id' => $result['transaction_id'] ?? '',
                    'reason' => $result['decline_message'] ?? $result['error_message'] ?? ''
                ];
            } else {
                // Initialize new payment
                $result = $this->nmiGateway->initializePayment(
                    $amount,
                    $currency,
                    null, // redirectUrl handled at controller level
                    $billingData
                );
                
                return [
                    'status' => $result['status'] === 'success' ? 'approved' : 'declined',
                    'form_url' => $result['form_url'] ?? '',
                    'reason' => $result['message'] ?? ''
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Payment gateway error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'failed',
                'reason' => 'Payment processing failed'
            ];
        }
    }

    public function processRefund(string $originalTransactionId, float $refundAmount): array
    {
        try {
            $result = $this->nmiGateway->processRefund($originalTransactionId, $refundAmount);
            
            return [
                'status' => $result['status'],
                'transaction_id' => $result['transaction_id'] ?? '',
                'message' => $result['message'] ?? ''
            ];
        } catch (\Exception $e) {
            $this->logger->error('Refund gateway error', [
                'original_transaction_id' => $originalTransactionId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Refund processing failed'
            ];
        }
    }

    public function initializePayment(
        float $amount,
        string $currency,
        string $redirectUrl,
        BillingInformation $billingInformation
    ): array {
        $billingData = $this->convertBillingInformationToArray($billingInformation);
        
        try {
            $result = $this->nmiGateway->initializePayment($amount, $currency, $redirectUrl, $billingData);
            
            return [
                'status' => $result['status'],
                'form_url' => $result['form_url'] ?? '',
                'message' => $result['message'] ?? ''
            ];
        } catch (\Exception $e) {
            $this->logger->error('Payment initialization error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Payment initialization failed'
            ];
        }
    }

    public function createPlan(CreatePlanDto $planDto): array
    {
        try {
            $result = $this->nmiGateway->createPlan($planDto);
            
            return [
                'status' => $result['status'],
                'plan_id' => $result['plan_id'] ?? $planDto->planId,
                'message' => $result['message'] ?? ''
            ];
        } catch (\Exception $e) {
            $this->logger->error('Plan creation gateway error', [
                'plan_name' => $planDto->planName,
                'plan_id' => $planDto->planId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Plan creation failed: ' . $e->getMessage()
            ];
        }
    }

    private function convertBillingInformationToArray(BillingInformation $billing): array
    {
        $address = $billing->getAddress();
        
        return [
            'first-name' => $billing->getFirstName(),
            'last-name' => $billing->getLastName(),
            'email' => $billing->getEmail()->getValue(),
            'address1' => $address->getStreet1(),
            'address2' => $address->getStreet2(),
            'city' => $address->getCity(),
            'state' => $address->getState(),
            'postal' => $address->getPostalCode(),
            'country' => $address->getCountry(),
            'phone' => $billing->getPhone()
        ];
    }
}
