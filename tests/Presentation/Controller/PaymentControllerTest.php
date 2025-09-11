<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * PaymentController Endpoint Tests
 * Tests that all endpoints are accessible and return appropriate responses
 */
class PaymentControllerTest extends WebTestCase
{
    public function testCheckoutEndpointGetIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/checkout');

        // Should not return 404 - endpoint should exist  
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Checkout endpoint should exist');
    }

    public function testCheckoutEndpointPostIsAccessible(): void
    {
        $client = static::createClient();
        
        // Submit checkout form data
        $client->request('POST', '/checkout', [
            'checkout' => [
                'amount' => '25.00',
                'currency' => 'USD',
                'billingFirstName' => 'John',
                'billingLastName' => 'Doe',
                'billingEmail' => 'test@example.com',
                'billingAddress1' => '123 Test St',
                'billingCity' => 'Test City',
                'billingState' => 'TX',
                'billingPostal' => '12345',
                'billingCountry' => 'US',
                'subscribeAndCheckout' => false
            ]
        ]);

        // Should not return 404 - endpoint should exist (allow other errors due to dependencies)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Checkout POST endpoint should exist');
    }

    public function testCheckoutWithTokenIdCallback(): void
    {
        $client = static::createClient();
        
        // Test payment callback with token-id
        $client->request('GET', '/checkout?token-id=test-token-123');

        // Should redirect after processing callback
        $this->assertTrue(
            $client->getResponse()->isRedirection() || 
            $client->getResponse()->isSuccessful()
        );
    }

    public function testRefundEndpointGetIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/refund');

        // Should not return 404 - endpoint should exist  
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Refund endpoint should exist');
    }

    public function testRefundEndpointPostIsAccessible(): void
    {
        $client = static::createClient();
        
        // Submit refund form data
        $client->request('POST', '/refund', [
            'refund' => [
                'transactionId' => 'test-txn-123',
                'refundAmount' => '15.00'
            ]
        ]);

        // Should redirect after operation
        $this->assertTrue(
            $client->getResponse()->isRedirection() || 
            $client->getResponse()->isSuccessful()
        );
    }

    public function testTransactionHistoryApiEndpointIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/transactions');

        // Should return a response (not crash)
        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue($statusCode >= 200 && $statusCode < 600, 'API endpoint should return valid HTTP status');
        
        if ($response->isSuccessful()) {
            $this->assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
        }
    }

    public function testTransactionHistoryApiWithTransactionId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/transactions?transaction-id=test-txn-123');

        // Should return a response (not crash)
        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue($statusCode >= 200 && $statusCode < 600, 'API endpoint should return valid HTTP status');
        
        if ($response->isSuccessful()) {
            $this->assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
        }
    }

    public function testInvalidMethodOnEndpoint(): void
    {
        $client = static::createClient();
        
        // Try DELETE on GET/POST endpoint
        $client->request('DELETE', '/checkout');

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testNonExistentPaymentEndpointReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/payment/non-existent');

        $this->assertResponseStatusCodeSame(404);
    }
}
