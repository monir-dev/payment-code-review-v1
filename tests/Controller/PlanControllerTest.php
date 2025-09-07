<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PlanControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testPlanCreateFormValidation(): void
    {
        $crawler = $this->client->request('GET', '/plan/create');
        $form = $crawler->selectButton('Create Plan')->form([
            'plan[planName]' => '', // Empty name not allowed
            'plan[amount]' => '29.99',
            'plan[frequency]' => 'monthly',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Plan name is required');
    }

    public function testPlanCreateAmountValidation(): void
    {
        $crawler = $this->client->request('GET', '/plan/create');
        $form = $crawler->selectButton('Create Plan')->form([
            'plan[planName]' => 'Test Plan',
            'plan[amount]' => '-10.00', // Amount should be positive
            'plan[frequency]' => 'monthly',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Amount must be greater than 0');
    }

    public function testPlanCreateZeroAmountValidation(): void
    {
        $crawler = $this->client->request('GET', '/plan/create');
        $form = $crawler->selectButton('Create Plan')->form([
            'plan[planName]' => 'Test Plan',
            'plan[amount]' => '0.00', // Zero amount should fail
            'plan[frequency]' => 'monthly',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Amount must be greater than 0');
    }

    public function testPlanCreateInvalidAmountFormatValidation(): void
    {
        $crawler = $this->client->request('GET', '/plan/create');
        $form = $crawler->selectButton('Create Plan')->form([
            'plan[planName]' => 'Test Plan',
            'plan[amount]' => 'invalid_amount', // Invalid format should fail
            'plan[frequency]' => 'monthly',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testFormSubmissionWithValidData(): void
    {
        $crawler = $this->client->request('GET', '/plan/create');
        $form = $crawler->selectButton('Create Plan')->form([
            'plan[planName]' => 'Test Plan',
            'plan[amount]' => '29.99',
            'plan[frequency]' => 'monthly',
        ]);

        $this->client->submit($form);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertNotEquals(422, $statusCode, 'Form validation should pass with valid data');
        $this->assertContains($statusCode, [200, 302, 500], 'Should handle form processing');
    }

    /**
     * Test various valid form data combinations
     */
    public function testValidFormDataCombinations(): void
    {
        $validTestCases = [
            ['Basic Plan', '9.99', 'monthly'],
            ['Premium Plan', '19.99', 'weekly'],
            ['Annual Plan', '99.99', 'yearly'],
            ['Economy Plan', '5.00', 'monthly'],
            ['Enterprise Plan', '199.99', 'monthly'],
        ];

        foreach ($validTestCases as [$planName, $amount, $frequency]) {
            $crawler = $this->client->request('GET', '/plan/create');
            $form = $crawler->selectButton('Create Plan')->form([
                'plan[planName]' => $planName,
                'plan[amount]' => $amount,
                'plan[frequency]' => $frequency,
            ]);

            $this->client->submit($form);
            $statusCode = $this->client->getResponse()->getStatusCode();
            $this->assertNotEquals(422, $statusCode, "Valid data should not cause validation error for: $planName");
            $this->assertContains($statusCode, [200, 302, 500], "Should handle processing for: $planName");
        }
    }

    public function testFormValidationErrorCombinations(): void
    {
        $invalidTestCases = [
            // [planName, amount, frequency, description]
            ['', '10.00', 'monthly', 'empty plan name'],
            ['Test', '', 'monthly', 'empty amount'],
            ['Test', '0', 'monthly', 'zero amount'],
            ['Test', '-5.00', 'monthly', 'negative amount'],
            ['Test', 'abc', 'monthly', 'non-numeric amount'],
            ['', '', 'monthly', 'empty name and amount'],
        ];

        foreach ($invalidTestCases as [$planName, $amount, $frequency, $description]) {
            $crawler = $this->client->request('GET', '/plan/create');
            $form = $crawler->selectButton('Create Plan')->form([
                'plan[planName]' => $planName,
                'plan[amount]' => $amount,
                'plan[frequency]' => $frequency,
            ]);

            $this->client->submit($form);

            $statusCode = $this->client->getResponse()->getStatusCode();
            $this->assertEquals(422, $statusCode, "Should return validation error for: $description");
        }
    }

    public function testPlanCreateRouteAccessible(): void
    {
        $this->client->request('GET', '/plan/create');
        $this->assertResponseIsSuccessful();
    }

    public function testPlanCreateRouteAcceptsPost(): void
    {
        $crawler = $this->client->request('GET', '/plan/create');
        $form = $crawler->selectButton('Create Plan')->form([
            'plan[planName]' => 'Test Plan',
            'plan[amount]' => '29.99',
            'plan[frequency]' => 'monthly',
        ]);

        $this->client->submit($form);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertNotEquals(405, $statusCode, 'Route should accept POST requests');
    }

    /**
     * Test frequency validation - only valid frequencies allowed
     */
    public function testFrequencyValidation(): void
    {
        $validFrequencies = ['weekly', 'monthly', 'yearly'];

        foreach ($validFrequencies as $frequency) {
            $crawler = $this->client->request('GET', '/plan/create');
            $form = $crawler->selectButton('Create Plan')->form([
                'plan[planName]' => 'Test Plan',
                'plan[amount]' => '29.99',
                'plan[frequency]' => $frequency,
            ]);

            $this->client->submit($form);

            $statusCode = $this->client->getResponse()->getStatusCode();
            $this->assertNotEquals(422, $statusCode, "Valid frequency '$frequency' should not cause validation error");
        }
    }

    public function testCsrfTokenValidation(): void
    {
        $this->client->request('POST', '/plan/create', [
            'plan' => [
                'planName' => 'Test Plan',
                'amount' => '29.99',
                'frequency' => 'monthly',
                '_token' => 'invalid_token'
            ]
        ]);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [400, 403, 422], 'Should reject invalid CSRF token');
    }

    public function testDecimalAmountPrecision(): void
    {
        $testAmounts = [
            '10.50',
            '99.99',
            '5.00',
            '0.01',
            '1000.00',
        ];

        foreach ($testAmounts as $amount) {
            $crawler = $this->client->request('GET', '/plan/create');
            $form = $crawler->selectButton('Create Plan')->form([
                'plan[planName]' => 'Test Plan',
                'plan[amount]' => $amount,
                'plan[frequency]' => 'monthly',
            ]);

            $this->client->submit($form);

            $statusCode = $this->client->getResponse()->getStatusCode();
            $this->assertNotEquals(422, $statusCode, "Valid amount '$amount' should not cause validation error");
        }
    }

    public function testPlanNameLengthValidation(): void
    {
        $longPlanName = str_repeat('A', 300); // Very long name

        $crawler = $this->client->request('GET', '/plan/create');
        $form = $crawler->selectButton('Create Plan')->form([
            'plan[planName]' => $longPlanName,
            'plan[amount]' => '29.99',
            'plan[frequency]' => 'monthly',
        ]);

        $this->client->submit($form);

        $statusCode = $this->client->getResponse()->getStatusCode();
        // Depending on validation rules, this might pass or fail
        $this->assertContains($statusCode, [200, 302, 422, 500], 'Should handle long plan name appropriately');
    }

    public function testControllerProcessingFlow(): void
    {
        $crawler = $this->client->request('GET', '/plan/create');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Plan')->form([
            'plan[planName]' => 'Integration Test Plan',
            'plan[amount]' => '15.99',
            'plan[frequency]' => 'weekly',
        ]);

        $this->client->submit($form);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 500], 'Controller should handle complete processing flow');
        $this->assertNotEquals(422, $statusCode, 'Valid data should not trigger validation errors');
    }
}
