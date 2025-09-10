<?php

namespace App\Service;

use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Entity\PaymentTransaction;
use App\Dto\CreatePlanDto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use DOMDocument;
use SimpleXMLElement;
use Exception;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://secure.nmi.com/merchants/resources/integration/integration_portal.php?tid=4a0d25146526480a75f81a71f616c04f#3step_methodology
 */
class NmiPaymentGateway implements PaymentGatewayInterface
{
    private const NMI_THREE_STEP_URL = 'https://secure.nmi.com/api/v2/three-step';
    private const NMI_TRANSACT_URL = 'https://secure.nmi.com/api/transact.php';

    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private HttpClientInterface $client;
    private string $nmiApiKey;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $paymentLogger,
        HttpClientInterface $client,
        string $nmiApiKey,
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $paymentLogger;
        $this->client = $client;
        $this->nmiApiKey = $nmiApiKey;
    }

    public function processPayment(
        string $transactionId,
        float $amount,
        string $currency,
        BillingInformation $billingInformation,
        ?string $gatewayToken = null
    ): array {
        // Convert domain objects to NMI format
        $billingInfo = [
            'first_name' => $billingInformation->getFirstName(),
            'last_name' => $billingInformation->getLastName(),
            'address1' => $billingInformation->getAddress()->getStreet1(),
            'address2' => $billingInformation->getAddress()->getStreet2(),
            'city' => $billingInformation->getAddress()->getCity(),
            'state' => $billingInformation->getAddress()->getState(),
            'postal' => $billingInformation->getAddress()->getPostalCode(),
            'country' => $billingInformation->getAddress()->getCountry(),
            'phone' => $billingInformation->getPhone(),
        ];

        if ($gatewayToken) {
            // Complete an existing payment with token
            $request = new \stdClass();
            $request->token_id = $gatewayToken;

            $result = $this->completeTransaction($request);

            return [
                'status' => $result['status'] === 'success' ? 'success' : 'failed',
                'transaction_id' => $result['transaction_id'] ?? '',
                'reason' => $result['decline_message'] ?? $result['error_message'] ?? ''
            ];
        } else {
            // Initialize new payment
            $result = $this->initializePaymentLegacy($amount, $currency, null, $billingInfo, []);

            return [
                'status' => $result['status'] === 'success' ? 'success' : 'failed',
                'reason' => $result['message'] ?? ''
            ];
        }
    }

    public function initializePayment(
        float $amount,
        string $currency,
        string $redirectUrl,
        BillingInformation $billingInformation
    ): array {
        // Convert domain object to array format
        $billingInfo = [
            'first-name' => $billingInformation->getFirstName(),
            'last-name' => $billingInformation->getLastName(),
            'email' => $billingInformation->getEmail()->getValue(),
            'address1' => $billingInformation->getAddress()->getStreet1(),
            'address2' => $billingInformation->getAddress()->getStreet2(),
            'city' => $billingInformation->getAddress()->getCity(),
            'state' => $billingInformation->getAddress()->getState(),
            'postal' => $billingInformation->getAddress()->getPostalCode(),
            'country' => $billingInformation->getAddress()->getCountry(),
            'phone' => $billingInformation->getPhone()
        ];

        return $this->initializePaymentLegacy($amount, $currency, $redirectUrl, $billingInfo, []);
    }

    /**
     * Step 1: Initialize payment and get form URL (Legacy method)
     */
    public function initializePaymentLegacy(
        $amount,
        $currency = 'USD',
        $redirectUrl = null,
        array $billingInfo = [],
        array $shippingInfo = [],
    ) {
        $xmlRequest = new DOMDocument('1.0', 'UTF-8');
        $xmlRequest->formatOutput = true;
        $xmlSale = $xmlRequest->createElement('sale');

        // Required fields
        $this->appendXmlNode($xmlRequest, $xmlSale, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'redirect-url', $redirectUrl ?: $_SERVER['HTTP_REFERER']);
        $this->appendXmlNode($xmlRequest, $xmlSale, 'amount', number_format($amount, 2, '.', ''));
        $this->appendXmlNode($xmlRequest, $xmlSale, 'ip-address', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'currency', $currency);

        // Optional order fields
        $this->appendXmlNode($xmlRequest, $xmlSale, 'order-id', uniqid('ORD-'));
        $this->appendXmlNode($xmlRequest, $xmlSale, 'order-description', 'Payment Gateway Order');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'tax-amount', '0.00');
        $this->appendXmlNode($xmlRequest, $xmlSale, 'shipping-amount', '0.00');

        // Billing information
        if (!empty($billingInfo)) {
            $xmlBillingAddress = $xmlRequest->createElement('billing');
            foreach ($billingInfo as $key => $value) {
                $this->appendXmlNode($xmlRequest, $xmlBillingAddress, $key, $value);
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

        // Send request
        $data = $this->sendApiRequest($xmlRequest, self::NMI_THREE_STEP_URL);

        // Parse response
        $gwResponse = @new SimpleXMLElement($data);
        if ((string)$gwResponse->result == 1) {
            return [
                'status' => 'success',
                'form_url' => (string)$gwResponse->{'form-url'},
            ];
        } else {
            $this->logger->error('Step 1 failed', ['response' => $data]);

            return [
                'status' => 'error',
                'message' => 'Failed to initialize payment',
            ];
        }
    }

    /**
     * Step 3: Complete transaction with token
     */
    public function completeTransaction($request)
    {
        $tokenId = $request->get('token-id');
        return $this->completeTransactionByTokenId($tokenId);
    }

    public function completeTransactionByTokenId(string $tokenId): array
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
            // Save transaction
            $transaction = new PaymentTransaction();
            $transaction->setCreatedAt(new \DateTime());
            $transaction->setUuid(Uuid::v4());
            $transaction->setUsedToken((string)$gwResponse->{'token-id'});
            $transaction->setTransactionId((string)$gwResponse->{'transaction-id'});
            $transaction->setAmount((float)$gwResponse->{'amount'});
            $transaction->setCurrencyCode((string)$gwResponse->{'currency'} ?: 'USD');
            $transaction->setPaymentStatus('Approved');
            $transaction->setLast4Digits(substr((string)$gwResponse->billing->{'cc-number'}, -4));
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            $this->logger->info('Payment successful', ['transaction_id' => (string)$gwResponse->{'transaction-id'}]);

            return [
                'status' => 'success',
                'transaction_id' => (string)$gwResponse->{'transaction-id'},
                'response' => $gwResponse,
            ];
        } elseif ((string)$gwResponse->result == 2) {
            $this->logger->warning('Payment declined', ['response' => $data]);

            return [
                'status' => 'declined',
                'decline_message' => (string)$gwResponse->{'result-text'},
            ];
        } else {
            $this->logger->error('Payment error', ['response' => $data]);

            return [
                'status' => 'error',
                'error_message' => (string)$gwResponse->{'result-text'},
            ];
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
    private function appendXmlNode($domDocument, $parentNode, $name, $value)
    {
        $childNode = $domDocument->createElement($name);
        $childNodeValue = $domDocument->createTextNode($value);
        $childNode->appendChild($childNodeValue);
        $parentNode->appendChild($childNode);
    }

    public function createPlan(CreatePlanDto $planDto): array
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

                return [
                    'status' => 'success',
                    'message' => $responseData['responsetext'] ?? 'Plan created successfully',
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => $responseData['responsetext'] ?? 'Failed to create plan',
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('NMI Plan creation API call failed', [
                'error' => $e->getMessage(),
                'plan_id' => $planDto->planId,
                'plan_name' => $planDto->planName,
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to create plan: ' . $e->getMessage(),
            ];
        }
    }

    public function createCustomerVault(array $billingInfo): array
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

                return [
                    'status' => 'success',
                    'customer_vault_id' => $responseData['customer_vault_id'],
                    'message' => 'Customer vault created successfully',
                ];
            } else {
                $this->logger->warning('Customer vault creation failed', [
                    'response' => $responseData
                ]);

                return [
                    'status' => 'error',
                    'message' => $responseData['responsetext'] ?? 'Failed to create customer vault',
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Customer vault creation exception', [
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to create customer vault: ' . $e->getMessage(),
            ];
        }
    }

    public function createSubscription(
        string $planId,
        string $customerVaultId,
        \DateTimeImmutable $startDate,
        array $billingInfo = []
    ): array {
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

                return [
                    'status' => 'success',
                    'subscription_id' => $responseData['subscription_id'] ?? '',
                    'transaction_id' => $responseData['transactionid'] ?? '',
                    'message' => 'Subscription created successfully with NMI',
                ];
            } else {
                $this->logger->warning('NMI subscription creation failed', [
                    'plan_id' => $planId,
                    'customer_vault_id' => $customerVaultId,
                    'response' => $responseData
                ]);

                return [
                    'status' => 'error',
                    'message' => $responseData['responsetext'] ?? 'Failed to create subscription with NMI',
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Subscription creation exception', [
                'plan_id' => $planId,
                'customer_vault_id' => $customerVaultId,
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to create subscription: ' . $e->getMessage(),
            ];
        }
    }

    public function cancelSubscription(string $subscriptionId): array
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

                return [
                    'status' => 'success',
                    'message' => 'Subscription cancelled successfully with NMI',
                ];
            } else {
                // Handle case where subscription was already cancelled in NMI
                $errorMessage = $responseData['responsetext'] ?? '';
                if (strpos($errorMessage, 'No recurring subscriptions found') !== false) {
                    $this->logger->info('Subscription already cancelled in NMI, treating as success', [
                        'subscription_id' => $subscriptionId,
                        'response' => $responseData
                    ]);

                    return [
                        'status' => 'success',
                        'message' => 'Subscription was already cancelled (syncing local status)',
                        'already_cancelled' => true,
                    ];
                }

                $this->logger->warning('NMI subscription cancellation failed', [
                    'subscription_id' => $subscriptionId,
                    'response' => $responseData
                ]);

                return [
                    'status' => 'error',
                    'message' => $errorMessage ?: 'Failed to cancel subscription with NMI',
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Subscription cancellation exception', [
                'subscription_id' => $subscriptionId,
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to cancel subscription: ' . $e->getMessage(),
            ];
        }
    }

    public function processRebilling(string $subscriptionId, string $customerVaultId, float $amount = null): array
    {
        if (empty($customerVaultId)) {
            return [
                'status' => 'error',
                'message' => 'Customer vault ID is required for rebilling. Subscription may need to be recreated.',
            ];
        }

        $formData = [
            'security_key' => $this->nmiApiKey,
            'type' => 'sale',
            'customer_vault_id' => $customerVaultId,
        ];

        if ($amount) {
            $formData['amount'] = number_format($amount, 2, '.', '');
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
                $this->logger->info('Rebilling processed successfully via customer vault', [
                    'subscription_id' => $subscriptionId,
                    'customer_vault_id' => $customerVaultId,
                    'transaction_id' => $responseData['transactionid'] ?? '',
                    'amount' => $responseData['amount'] ?? $amount
                ]);

                return [
                    'status' => 'success',
                    'transaction_id' => $responseData['transactionid'] ?? '',
                    'amount' => (float)($responseData['amount'] ?? $amount ?? 0),
                    'message' => 'Rebilling processed successfully',
                ];
            } else {
                $this->logger->warning('Rebilling failed via customer vault', [
                    'subscription_id' => $subscriptionId,
                    'customer_vault_id' => $customerVaultId,
                    'response' => $responseData
                ]);

                return [
                    'status' => 'declined',
                    'message' => $responseData['responsetext'] ?? 'Rebilling failed',
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Rebilling exception', [
                'subscription_id' => $subscriptionId,
                'customer_vault_id' => $customerVaultId,
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to process rebilling: ' . $e->getMessage(),
            ];
        }
    }

    public function processRefund(string $originalTransactionId, float $refundAmount): array
    {
        if ($refundAmount <= 0) {
            return ['status' => 'error', 'message' => 'Refund amount must be positive.'];
        }

        $xmlRequest = new DOMDocument('1.0', 'UTF-8');

        $xmlRequest->formatOutput = true;
        $xmlRefund = $xmlRequest->createElement('refund');

        $this->appendXmlNode($xmlRequest, $xmlRefund, 'api-key', $this->nmiApiKey);
        $this->appendXmlNode($xmlRequest, $xmlRefund, 'transaction-id', $originalTransactionId);
        $this->appendXmlNode($xmlRequest, $xmlRefund, 'amount', $refundAmount);

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

            return ['status' => 'success', 'transaction_id' => (string)$gwResponse->{'transaction-id'}];
        } else {
            $message = (string)$gwResponse->{'responsetext'} ?? 'Refund failed.';
            $this->logger->warning(
                'Refund failed',
                ['response_text' => $message, 'original_transaction_id' => $originalTransactionId],
            );

            return ['status' => 'error', 'message' => $message];
        }
    }
}
