<?php

namespace App\Service;

use App\Entity\PaymentTransaction;
use App\Entity\Plan;
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
class NmiPaymentGateway
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

    /**
     * Step 1: Initialize payment and get form URL
     */
    public function initializePayment(
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

    public function processRefund($originalTransactionId, $refundAmount)
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

        if ((string)$gwResponse->{'result'} !== '1') {
            $this->logger->info(
                'Refund successful',
                [
                    'transaction_id' => (string)$gwResponse->{'transaction-id'},
                    'original_transaction_id' => $originalTransactionId,
                ],
            );

            return ['status' => 'success', 'transaction_id' => (string)$gwResponse->{'transaction-id'}];
        } else {
            $message = $responseArray['responsetext'] ?? 'Refund failed.';
            $this->logger->warning(
                'Refund failed',
                ['response_text' => $message, 'original_transaction_id' => $originalTransactionId],
            );

            return ['status' => 'error', 'message' => $message];
        }
    }
}
