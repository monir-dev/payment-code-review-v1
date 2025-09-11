<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * PlanController Endpoint Tests
 * Tests that all endpoints are accessible and return appropriate responses
 */
class PlanControllerTest extends WebTestCase
{
    public function testIndexEndpointIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/plans'); // Route is /plan + 's' in controller

        // Should not return 404 - endpoint should exist  
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Plans index endpoint should exist');
    }

    public function testCreateEndpointGetIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/plan/create');

        // Should either be successful or redirect
        $this->assertTrue(
            $client->getResponse()->isSuccessful() ||
            $client->getResponse()->isRedirection()
        );
    }

    public function testCreateEndpointPostIsAccessible(): void
    {
        $client = static::createClient();
        
        // Submit plan form data
        $client->request('POST', '/plan/create', [
            'plan' => [
                'planName' => 'Test Plan',
                'amount' => '29.99',
                'frequency' => 'monthly',
                'currencyCode' => 'USD'
            ]
        ]);

        // Should redirect after creation or show form with errors
        $this->assertTrue(
            $client->getResponse()->isRedirection() || 
            $client->getResponse()->isSuccessful()
        );
    }

    public function testToggleStatusEndpointIsAccessible(): void
    {
        $client = static::createClient();
        
        // Test status toggle with dummy plan ID
        $client->request('POST', '/plan/test-plan-123/toggle-status');

        // Should redirect after operation  
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    public function testApiCreateEndpointIsAccessible(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/plan/api/create', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'planName' => 'API Test Plan',
            'amount' => 39.99,
            'frequency' => 'monthly',
            'currencyCode' => 'USD'
        ]));

        // Should return JSON response
        $this->assertTrue($client->getResponse()->getStatusCode() < 500, 'API endpoint should not crash');
        
        $response = $client->getResponse();
        if ($response->isSuccessful()) {
            $this->assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
        }
    }

    public function testApiCreateEndpointWithInvalidData(): void
    {
        $client = static::createClient();
        
        // Send JSON with missing required fields
        $client->request('POST', '/plan/api/create', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'planName' => 'Test Plan'
            // Missing required fields like amount, frequency, etc.
        ]));

        // Should return 400 for validation error
        $this->assertTrue($client->getResponse()->getStatusCode() >= 400);
    }

    public function testApiCreateEndpointWithInvalidJson(): void
    {
        $client = static::createClient();
        
        // Send invalid JSON to API endpoint
        $client->request('POST', '/plan/api/create', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], 'invalid json content');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testInvalidMethodOnEndpoint(): void
    {
        $client = static::createClient();
        
        // Try GET on POST-only endpoint
        $client->request('GET', '/plan/test-plan-123/toggle-status');

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testNonExistentPlanEndpointReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/plan/non-existent');

        $this->assertResponseStatusCodeSame(404);
    }
}
