<?php

namespace App\Service;

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
use App\Dto\CreatePlanDto;
use Psr\Log\LoggerInterface;
use DOMDocument;
use SimpleXMLElement;
use Exception;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://secure.nmi.com/merchants/resources/integration/integration_portal.php?tid=4a0d25146526480a75f81a71f616c04f#3step_methodology
 */
class NmiPaymentGateway implements PaymentGatewayInterface
{
    private const NMI_THREE_STEP_URL = 'https://secure.nmi.com/api/v2/three-step';
    private const NMI_TRANSACT_URL = 'https://secure.nmi.com/api/transact.php';

    private LoggerInterface $logger;
    private HttpClientInterface $client;
    private string $nmiApiKey;

    public function __construct(
        LoggerInterface $paymentLogger,
        HttpClientInterface $client,
        string $nmiApiKey,
    ) {
        $this->logger = $paymentLogger;
        $this->client = $client;
        $this->nmiApiKey = $nmiApiKey;
    }

    public function processPayment(
        string $transactionId,
        Money $amount,
        string $currency,
        BillingInformation $billingInformation,
        ?string $gatewayToken = null
    ): ProcessPaymentResponse {
        // Convert domain objects to NMI format
        $billingInfo = $billingInformation->toArray();

        if ($gatewayToken) {
            // Complete an existing payment with token
            $result = $this->completeTransaction($gatewayToken);

            return new ProcessPaymentResponse(
                status: $result->isSuccessful() ? 'success' : 'failed',
                transactionId: $result->transactionId ?? '',
                reason: $result->declineMessage ?? $result->errorMessage ?? '',
                rawResponse: $result->rawResponse
            );
        } else {
            // Initialize new payment
            $result = $this->initializePayment($amount, $currency, null, $billingInformation, []);

            return new ProcessPaymentResponse(
                status: $result->isSuccessful() ? 'success' : 'failed',
                reason: $result->declineMessage ?? $result->errorMessage ?? '',
                rawResponse: $result->rawResponse
            );
        }
    }

    public function initializePayment(
        Money $amount,
        string $currency,
        ?string $redirectUrl = null,
        ?BillingInformation $billingInformation = null,
        array $shippingInfo = []
    ): InitializePaymentResponse {
        $xmlRequest = new DOMDocument('1.0', 'UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlSale = $xmlRequest->createElement('sale');

        // Required fields
        $this->appendXmlNode($xmlRequest, $xmlSale, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'redirect-url', $redirectUrl ?: $_SERVER['HTTP_REFERER']);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'amount', number_format($amount->getAmount(), 2, '.', ''));
        $this->appendXmlNode($xmlRequest, $xmlSale, 'ip-address', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'currency', $currency);

        // Optional order fields
        $this->appendXmlNode($xmlRequest, $xmlSale, 'order-id', uniqid('ORD-'));
        $this->appendXmlNode($xmlRequest, $xmlSale, 'order-description', 'Payment Gateway Order');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'tax-amount', '0.00');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'shipping-amount', '0.00');

        // Billing information
        if ($billingInformation) {
            $billingInfo = $billingInformation->toArray();
            $xmlBillingAddress = $xmlRequest->createElement('billing');
            foreach ($billingInfo as $key => $value) {
                if ($value !== null) {
                    $this->appendXmlNode($xmlRequest, $xmlBillingAddress, $key, $value);
                }
            }
            $xmlSale->appendChild($xmlBillingAddress);
        }

        // Shipping information
        if (!empty($shippingInfo)) {
            $xmlShippingAddress = $xmlRequest->createElement('shipping');
            foreach ($shippingInfo as $key => $value) {
                $this->appendXmlNode($xmlRequest, $xmlShippingAddress, $key, $value);
            }
            $xmlSale->appendChild($xmlShippingAddress);
        }

        $xmlRequest->appendChild($xmlSale);

        // Log the outgoing XML request for debugging
        $xmlString = $xmlRequest->saveXML();
        $this->logger->info('NMI Payment Initialization Request', [
            'xml_request' => $xmlString,
            'amount' => $amount->getAmount(),
            'currency' => $currency,
            'has_billing_info' => $billingInformation !== null
        ]);

        // Send request
        $data = $this->sendApiRequest($xmlRequest, self::NMI_THREE_STEP_URL);

        // Log the raw response for debugging
        $this->logger->info('NMI Payment Initialization Raw Response', [
            'raw_response' => $data,
            'response_length' => strlen($data)
        ]);

        // Parse response
        $gwResponse = @new SimpleXMLElement($data);
        
        if ($gwResponse === false) {
            $this->logger->error('Failed to parse NMI XML response', [
                'raw_response' => $data,
                'xml_errors' => libxml_get_errors()
            ]);
            return new InitializePaymentResponse(
                status: 'error',
                message: 'Payment initialization failed: Invalid response from payment gateway'
            );
        }

        $result = (string)$gwResponse->result;
        $this->logger->info('NMI Response Parsed', [
            'result' => $result,
            'responsetext' => (string)($gwResponse->responsetext ?? 'no responsetext'),
            'form_url' => (string)($gwResponse->{'form-url'} ?? 'no form-url'),
            'all_elements' => array_keys((array)$gwResponse)
        ]);

        if ($result == '1') {
            $formUrl = (string)$gwResponse->{'form-url'};
            if (empty($formUrl)) {
                $this->logger->error('NMI success response missing form URL', [
                    'response' => $data,
                    'parsed_elements' => (array)$gwResponse
                ]);
                return new InitializePaymentResponse(
                    status: 'error',
                    message: 'Payment initialization failed: No form URL received'
                );
            }
            
            return new InitializePaymentResponse(
                status: 'success',
                formUrl: $formUrl
            );
        } else {
            $responseText = (string)($gwResponse->responsetext ?? '');
            if (empty($responseText)) {
                // Check for other possible error fields (NMI uses result-text not responsetext)
                $possibleErrors = [
                    'result-text' => (string)($gwResponse->{'result-text'} ?? ''),
                    'error' => (string)($gwResponse->error ?? ''),
                    'message' => (string)($gwResponse->message ?? ''),
                    'errortext' => (string)($gwResponse->errortext ?? '')
                ];
                $responseText = implode(', ', array_filter($possibleErrors));
                if (empty($responseText)) {
                    $responseText = 'No error message provided by payment gateway';
                }
            }
            
            $this->logger->error('NMI Payment Initialization Failed', [
                'result' => $result,
                'responsetext' => $responseText,
                'raw_response' => $data,
                'parsed_response' => (array)$gwResponse
            ]);

            return new InitializePaymentResponse(
                status: 'error',
                message: 'Payment initialization failed: ' . $responseText
            );
        }
    }

    /**
     * Step 3: Complete transaction with token
     */
    public function completeTransaction(string $tokenId): CompleteTransactionResponse
    {
        return $this->completeTransactionByTokenId($tokenId);
    }

    public function completeTransactionByTokenId(string $tokenId, ?string $subscriptionId = null): CompleteTransactionResponse
    {
        $xmlRequest = new DOMDocument('1.0', 'UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlCompleteTransaction = $xmlRequest->createElement('complete-action');

        $this->appendXmlNode($xmlRequest, $xmlCompleteTransaction, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlCompleteTransaction, 'token-id', $tokenId);

        $xmlRequest->appendChild($xmlCompleteTransaction);

        // Send request
        $data = $this->sendApiRequest($xmlRequest, self::NMI_THREE_STEP_URL);

        // Parse response
        $gwResponse = @new SimpleXMLElement($data);

        if ((string)$gwResponse->result == 1) {
            $transactionId = (string)$gwResponse->{'transaction-id'};

            return new CompleteTransactionResponse(
                status: 'success',
                transactionId: $transactionId,
                amount: Money::fromFloat((float)(string)$gwResponse->{'amount'}, (string)$gwResponse->{'currency'} ?: 'USD'),
                currency: (string)$gwResponse->{'currency'} ?: 'USD',
                tokenId: (string)$gwResponse->{'token-id'},
                billingInfo: ['cc-number' => (string)$gwResponse->billing->{'cc-number'}],
                rawResponse: json_decode(json_encode($gwResponse), true)
            );
        } elseif ((string)$gwResponse->result == 2) {
            $this->logger->warning('Payment declined', ['response' => $data]);

            return new CompleteTransactionResponse(
                status: 'declined',
                transactionId: '',
                declineMessage: (string)$gwResponse->{'result-text'},
                rawResponse: json_decode(json_encode($gwResponse), true)
            );
        } else {
            $this->logger->error('Payment error', ['response' => $data]);

            return new CompleteTransactionResponse(
                status: 'error',
                transactionId: '',
                errorMessage: (string)$gwResponse->{'result-text'},
                rawResponse: json_decode(json_encode($gwResponse), true)
            );
        }
    }

    private function sendApiRequest(\DOMDocument $xmlRequest, string $gatewayURL): string
    {
        try {
            $response = $this->client->request('POST', $gatewayURL, [
                'headers' => [
                    'Content-Type' => 'text/xml',
                ],
                'body' => $xmlRequest->saveXML(),
                'timeout' => 30,
                'verify_peer' => false, // Should be true in production
            ]);

            return $response->getContent();
        } catch (TransportExceptionInterface $e) {
            throw new Exception("Request failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Helper function to append XML nodes
     */
    private function appendXmlNode(DOMDocument $domDocument, \DOMElement $parentNode, string $name, string $value): void
    {
        $childNode = $domDocument->createElement($name);
        $childNodeValue = $domDocument->createTextNode($value);
        $childNode->appendChild($childNodeValue);
        $parentNode->appendChild($childNode);
    }

    public function createPlan(CreatePlanDto $planDto): CreatePlanResponse
    {
        // Use form POST data instead of XML for transact.php endpoint
        $formData = [
            'security_key' => $this->nmiApiKey,
            'recurring' => 'add_plan',
            'plan_id' => $planDto->planId,
            'plan_name' => $planDto->planName,
            'plan_amount' => number_format($planDto->amount, 2, '.', ''),
            'plan_payments' => '0', // 0 = indefinite
            'day_frequency' => $planDto->dayFrequency,
        ];

        try {
            $response = $this->client->request('POST', self::NMI_TRANSACT_URL, [
                'body' => http_build_query($formData),
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            $data = $response->getContent();

            // Parse response - NMI returns key=value pairs
            $responseData = [];
            parse_str($data, $responseData);

            if (($responseData['response'] ?? '') == '1') {
                $this->logger->info('Plan created successfully with NMI', [
                    'plan_id' => $planDto->planId,
                    'plan_name' => $planDto->planName,
                    'response' => $responseData['responsetext'] ?? ''
                ]);

                return new CreatePlanResponse(
                    status: 'success',
                    planId: $planDto->planId,
                    message: $responseData['responsetext'] ?? 'Plan created successfully',
                    rawResponse: $responseData
                );
            } else {
                return new CreatePlanResponse(
                    status: 'error',
                    message: $responseData['responsetext'] ?? 'Failed to create plan',
                    rawResponse: $responseData
                );
            }
        } catch (Exception $e) {
            $this->logger->error('NMI Plan creation API call failed', [
                'error' => $e->getMessage(),
                'plan_id' => $planDto->planId,
                'plan_name' => $planDto->planName,
            ]);

            return new CreatePlanResponse(
                status: 'error',
                message: 'Failed to create plan: ' . $e->getMessage()
            );
        }
    }

    public function createCustomerVault(array $billingInfo): CreateCustomerVaultResponse
    {
        $formData = [
            'security_key' => $this->nmiApiKey,
            'customer_vault' => 'add_customer',
            // Credit card info - in production this would come from user input
            'ccnumber' => '4111111111111111',
            'ccexp' => '1225',
            'cvv' => '999',
        ];

        // Add billing info
        foreach ($billingInfo as $key => $value) {
            if (!empty($value)) {
                $formData[$key] = $value;
            }
        }

        try {
            $response = $this->client->request('POST', self::NMI_TRANSACT_URL, [
                'body' => http_build_query($formData),
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            $data = $response->getContent();

            // Parse response
            $responseData = [];
            parse_str($data, $responseData);

            if (($responseData['response'] ?? '') == '1' && !empty($responseData['customer_vault_id'])) {
                $this->logger->info('Customer vault created successfully', [
                    'customer_vault_id' => $responseData['customer_vault_id'],
                    'response' => $responseData['responsetext'] ?? ''
                ]);

                return new CreateCustomerVaultResponse(
                    status: 'success',
                    customerVaultId: $responseData['customer_vault_id'],
                    message: 'Customer vault created successfully',
                    rawResponse: $responseData
                );
            } else {
                $this->logger->warning('Customer vault creation failed', [
                    'response' => $responseData
                ]);

                return new CreateCustomerVaultResponse(
                    status: 'error',
                    message: $responseData['responsetext'] ?? 'Failed to create customer vault',
                    rawResponse: $responseData
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Customer vault creation exception', [
                'message' => $e->getMessage()
            ]);

            return new CreateCustomerVaultResponse(
                status: 'error',
                message: 'Failed to create customer vault: ' . $e->getMessage()
            );
        }
    }

    public function createSubscription(
        string $planId,
        string $customerVaultId,
        \DateTimeImmutable $startDate,
        array $billingInfo = []
    ): CreateSubscriptionResponse {
        $formData = [
            'security_key' => $this->nmiApiKey,
            'recurring' => 'add_subscription',
            'plan_id' => $planId,
            'start_date' => $startDate->format('Ymd'),
            'customer_vault_id' => $customerVaultId,
        ];

        // Add billing information if provided
        foreach ($billingInfo as $key => $value) {
            if (!empty($value)) {
                $formData[$key] = $value;
            }
        }

        try {
            $response = $this->client->request('POST', self::NMI_TRANSACT_URL, [
                'body' => http_build_query($formData),
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            $data = $response->getContent();

            // Parse response
            $responseData = [];
            parse_str($data, $responseData);

            if (($responseData['response'] ?? '') == '1') {
                $this->logger->info('Subscription created successfully via NMI API', [
                    'plan_id' => $planId,
                    'customer_vault_id' => $customerVaultId,
                    'subscription_id' => $responseData['subscription_id'] ?? '',
                    'transaction_id' => $responseData['transactionid'] ?? '',
                    'start_date' => $startDate->format('Y-m-d')
                ]);

                return new CreateSubscriptionResponse(
                    status: 'success',
                    subscriptionId: $responseData['subscription_id'] ?? '',
                    transactionId: $responseData['transactionid'] ?? '',
                    message: 'Subscription created successfully with NMI',
                    rawResponse: $responseData
                );
            } else {
                $this->logger->warning('NMI subscription creation failed', [
                    'plan_id' => $planId,
                    'customer_vault_id' => $customerVaultId,
                    'response' => $responseData
                ]);

                return new CreateSubscriptionResponse(
                    status: 'error',
                    message: $responseData['responsetext'] ?? 'Failed to create subscription with NMI',
                    rawResponse: $responseData
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Subscription creation exception', [
                'plan_id' => $planId,
                'customer_vault_id' => $customerVaultId,
                'message' => $e->getMessage()
            ]);

            return new CreateSubscriptionResponse(
                status: 'error',
                message: 'Failed to create subscription: ' . $e->getMessage()
            );
        }
    }

    public function cancelSubscription(string $subscriptionId): CancelSubscriptionResponse
    {
        $formData = [
            'security_key' => $this->nmiApiKey,
            'recurring' => 'delete_subscription',
            'subscription_id' => $subscriptionId,
        ];

        try {
            $response = $this->client->request('POST', self::NMI_TRANSACT_URL, [
                'body' => http_build_query($formData),
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            $data = $response->getContent();

            // Parse response
            $responseData = [];
            parse_str($data, $responseData);

            if (($responseData['response'] ?? '') == '1') {
                $this->logger->info('Subscription cancelled via NMI API', [
                    'subscription_id' => $subscriptionId,
                    'response' => $responseData['responsetext'] ?? ''
                ]);

                return new CancelSubscriptionResponse(
                    status: 'success',
                    message: 'Subscription cancelled successfully with NMI'
                );
            } else {
                // Handle case where subscription was already cancelled in NMI
                $errorMessage = $responseData['responsetext'] ?? '';
                if (strpos($errorMessage, 'No recurring subscriptions found') !== false) {
                    $this->logger->info('Subscription already cancelled in NMI, treating as success', [
                        'subscription_id' => $subscriptionId,
                        'response' => $responseData
                    ]);

                    return new CancelSubscriptionResponse(
                        status: 'success',
                        message: 'Subscription was already cancelled (syncing local status)',
                        alreadyCancelled: true
                    );
                }

                $this->logger->warning('NMI subscription cancellation failed', [
                    'subscription_id' => $subscriptionId,
                    'response' => $responseData
                ]);

                return new CancelSubscriptionResponse(
                    status: 'error',
                    message: $errorMessage ?: 'Failed to cancel subscription with NMI'
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Subscription cancellation exception', [
                'subscription_id' => $subscriptionId,
                'message' => $e->getMessage()
            ]);

            return new CancelSubscriptionResponse(
                status: 'error',
                message: 'Failed to cancel subscription: ' . $e->getMessage()
            );
        }
    }

    public function processRebilling(string $subscriptionId, string $customerVaultId, ?Money $amount = null): RebillResponse
    {
        if (empty($customerVaultId)) {
            return new RebillResponse(
                status: 'error',
                subscriptionId: $subscriptionId,
                message: 'Customer vault ID is required for rebilling. Subscription may need to be recreated.'
            );
        }

        $formData = [
            'security_key' => $this->nmiApiKey,
            'type' => 'sale',
            'customer_vault_id' => $customerVaultId,
        ];

        if ($amount) {
            $formData['amount'] = number_format($amount->getAmount(), 2, '.', '');
        }

        try {
            $response = $this->client->request('POST', self::NMI_TRANSACT_URL, [
                'body' => http_build_query($formData),
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            $data = $response->getContent();

            // Parse response
            $responseData = [];
            parse_str($data, $responseData);

            if (($responseData['response'] ?? '') == '1') {
                $transactionId = $responseData['transactionid'] ?? '';
                $processedAmount = (float)($responseData['amount'] ?? $amount?->getAmount() ?? 0);

                $this->logger->info('Rebilling processed successfully', [
                    'subscription_id' => $subscriptionId,
                    'customer_vault_id' => $customerVaultId,
                    'transaction_id' => $transactionId,
                    'amount' => $processedAmount
                ]);

                return new RebillResponse(
                    status: 'success',
                    transactionId: $transactionId,
                    amount: Money::fromFloat($processedAmount, 'USD'),
                    subscriptionId: $subscriptionId,
                    message: 'Rebilling processed successfully'
                );
            } else {
                $this->logger->warning('Rebilling failed via customer vault', [
                    'subscription_id' => $subscriptionId,
                    'customer_vault_id' => $customerVaultId,
                    'response' => $responseData
                ]);

                return new RebillResponse(
                    status: 'declined',
                    subscriptionId: $subscriptionId,
                    message: $responseData['responsetext'] ?? 'Rebilling failed'
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Rebilling exception', [
                'subscription_id' => $subscriptionId,
                'customer_vault_id' => $customerVaultId,
                'message' => $e->getMessage()
            ]);

            return new RebillResponse(
                status: 'error',
                subscriptionId: $subscriptionId,
                message: 'Failed to process rebilling: ' . $e->getMessage()
            );
        }
    }

    public function processRefund(string $originalTransactionId, Money $refundAmount): RefundResponse
    {
        if ($refundAmount->isZero()) {
            return new RefundResponse(
                status: 'error',
                refundAmount: $refundAmount->getAmount(),
                originalTransactionId: $originalTransactionId,
                message: 'Refund amount must be positive.'
            );
        }

        $xmlRequest = new DOMDocument('1.0', 'UTF-8');

        $xmlRequest->formatOutput = true;
        $xmlRefund = $xmlRequest->createElement('refund');

        $this->appendXmlNode($xmlRequest, $xmlRefund, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlRefund, 'transaction-id', $originalTransactionId);
        $this->appendXmlNode($xmlRequest, $xmlRefund, 'amount', $refundAmount->getAmount());

        $xmlRequest->appendChild($xmlRefund);

        $xml = $this->sendApiRequest($xmlRequest, self::NMI_THREE_STEP_URL);
        $gwResponse = @new SimpleXMLElement((string)$xml);

        if ((string)$gwResponse->{'result'} === '1') {
            $this->logger->info(
                'Refund successful',
                [
                    'transaction_id' => (string)$gwResponse->{'transaction-id'},
                    'original_transaction_id' => $originalTransactionId,
                ],
            );

            return new RefundResponse(
                status: 'success',
                transactionId: (string)$gwResponse->{'transaction-id'},
                refundAmount: $refundAmount->getAmount(),
                originalTransactionId: $originalTransactionId
            );
        } else {
            $message = (string)$gwResponse->{'responsetext'} ?? 'Refund failed.';
            $this->logger->warning(
                'Refund failed',
                ['response_text' => $message, 'original_transaction_id' => $originalTransactionId],
            );

            return new RefundResponse(
                status: 'error',
                refundAmount: $refundAmount->getAmount(),
                originalTransactionId: $originalTransactionId,
                message: $message
            );
        }
    }

}
