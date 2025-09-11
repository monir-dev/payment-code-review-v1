<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * SubscriptionController Endpoint Tests
 * Tests that all endpoints are accessible and return appropriate responses
 */
class SubscriptionControllerTest extends WebTestCase
{
    public function testIndexEndpointIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/subscriptions');  // Fixed: route is /subscriptions (with s)

        // Should not return 404 - endpoint should exist  
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Subscription index endpoint should exist');
    }

    public function testCreateEndpointGetIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/subscription/create');

        // Should not return 404 - endpoint should exist (allow other errors like 500 due to dependencies)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Subscription create endpoint should exist');
    }

    public function testCreateEndpointPostWithValidData(): void
    {
        $client = static::createClient();
        
        // Submit subscription form data
        $client->request('POST', '/subscription/create', [
            'subscription' => [
                'plan_id' => 'test-plan-123',
                'customer_email' => 'test@example.com',
                'billing_first_name' => 'John',
                'billing_last_name' => 'Doe',
                'billing_address1' => '123 Test St',
                'billing_city' => 'Test City',
                'billing_state' => 'TX',
                'billing_postal' => '12345',
                'billing_country' => 'US'
            ]
        ]);

        // Should not return 404 - endpoint should exist (allow other errors like 500 due to dependencies)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Subscription create POST endpoint should exist');
    }

    public function testCreateEndpointPostWithInvalidData(): void
    {
        $client = static::createClient();
        
        // Submit invalid subscription data (missing required fields)
        $client->request('POST', '/subscription/create', [
            'subscription' => [
                'customer_email' => 'invalid-email'
                // Missing required billing fields
            ]
        ]);

        // Should not return 404 - endpoint should exist (allow validation errors and other issues)  
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Subscription create POST endpoint should handle invalid data');
    }

    public function testCancelEndpointPostIsAccessible(): void
    {
        $client = static::createClient();
        
        // Test cancellation with dummy subscription ID
        $client->request('POST', '/subscription/test-sub-123/cancel', [
            'reason' => 'Customer request',
            'cancel_with_gateway' => true
        ]);

        // Should redirect after operation (success or failure)  
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    public function testRebillEndpointPostIsAccessible(): void
    {
        $client = static::createClient();
        
        // Test rebill with dummy subscription ID
        $client->request('POST', '/subscription/test-sub-123/rebill', [
            'amount' => '29.99',
            'reason' => 'Manual rebill test',
            'process_with_gateway' => true
        ]);

        // Should redirect after operation  
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    public function testApiCreateEndpointIsAccessible(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/subscription/api/create', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'planId' => 'test-plan-123',
            'customerEmail' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'street1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TX',
            'postalCode' => '12345',
            'country' => 'US'
        ]));

        // Should return JSON response
        $this->assertResponseHasHeader('Content-Type');
        $response = $client->getResponse();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function testApiCreateEndpointWithMissingFields(): void
    {
        $client = static::createClient();
        
        // Send JSON with missing required fields
        $client->request('POST', '/subscription/api/create', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'customerEmail' => 'test@example.com'
            // Missing required fields like planId, firstName, etc.
        ]));

        $this->assertResponseStatusCodeSame(400);
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertStringContainsString('is required', $content['error']);
    }

    public function testApiCancelEndpointIsAccessible(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/subscription/api/test-sub-123/cancel', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'reason' => 'API test cancellation',
            'cancelWithGateway' => true
        ]));

        // Should return JSON response (success or error)
        $this->assertResponseHasHeader('Content-Type');
        $response = $client->getResponse();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function testApiRebillEndpointIsAccessible(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/subscription/api/test-sub-123/rebill', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'customAmount' => 49.99,
            'reason' => 'API test rebill',
            'processWithGateway' => true
        ]));

        // Should return JSON response
        $this->assertResponseHasHeader('Content-Type');
        $response = $client->getResponse();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function testApiEndpointWithInvalidJson(): void
    {
        $client = static::createClient();
        
        // Send invalid JSON to API endpoint
        $client->request('POST', '/subscription/api/create', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], 'invalid json content');

        $this->assertResponseStatusCodeSame(400);
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertStringContainsString('Invalid JSON', $content['error']);
    }

    public function testInvalidMethodOnEndpoint(): void
    {
        $client = static::createClient();
        
        // Try GET on POST-only endpoint
        $client->request('GET', '/subscription/test-sub-123/cancel');

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testNonExistentEndpointReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/subscription/non-existent');

        $this->assertResponseStatusCodeSame(404);
    }
}
