<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\NmiPaymentGateway;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Billing\Dto\CreatePlanDto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class NmiPaymentGatewayTest extends TestCase
{
    private LoggerInterface $mockLogger;
    private HttpClientInterface $mockHttpClient;
    private NmiPaymentGateway $gateway;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
        $this->gateway = new NmiPaymentGateway($this->mockLogger, $this->mockHttpClient, 'test-key');
    }

    public function testCompleteTransactionSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            '<nm_response><result>1</result><transactionid>txn-123</transactionid></nm_response>'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $result = $this->gateway->completeTransaction('token123');
        
        $this->assertEquals('success', $result->status);
    }

    public function testProcessRebillingSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=1&transactionid=txn-rebill-456&amount=29.99'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $result = $this->gateway->processRebilling(
            'sub-123',
            'vault-456', 
            Money::fromFloat(29.99, 'USD')
        );
        
        $this->assertEquals('success', $result->status);
        $this->assertEquals('sub-123', $result->subscriptionId);
        $this->assertEquals('txn-rebill-456', $result->transactionId);
        $this->assertEquals(29.99, $result->amount->getAmount());
    }

    public function testProcessRebillingFailedWithMissingVaultId(): void
    {
        // No HTTP call should be made with empty vault ID
        $this->mockHttpClient->expects($this->never())->method('request');
        
        $result = $this->gateway->processRebilling('sub-123', '', Money::fromFloat(29.99, 'USD'));
        
        $this->assertEquals('error', $result->status);
        $this->assertStringContainsString('Customer vault ID is required', $result->message);
    }

    public function testProcessRebillingFailedTransaction(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=2&responsetext=DECLINE&transactionid='
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $result = $this->gateway->processRebilling('sub-123', 'vault-456', Money::fromFloat(29.99, 'USD'));
        
        $this->assertEquals('declined', $result->status);
        $this->assertStringContainsString('DECLINE', $result->message);
    }

    public function testCreateSubscriptionSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=1&subscription_id=nmi-sub-789&responsetext=SUCCESS'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $startDate = new \DateTimeImmutable('2024-01-01');
        $result = $this->gateway->createSubscription('plan-123', 'vault-456', $startDate, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ]);
        
        $this->assertEquals('success', $result->status);
        $this->assertEquals('nmi-sub-789', $result->subscriptionId);
        $this->assertStringContainsString('Subscription created successfully', $result->message);
    }

    public function testCreateSubscriptionFailed(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=3&responsetext=Invalid plan ID'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $startDate = new \DateTimeImmutable('2024-01-01');
        $result = $this->gateway->createSubscription('invalid-plan', 'vault-456', $startDate);
        
        $this->assertEquals('error', $result->status);
        $this->assertStringContainsString('Invalid plan ID', $result->message);
    }

    public function testCancelSubscriptionSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=1&responsetext=Subscription Canceled Successfully'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $result = $this->gateway->cancelSubscription('nmi-sub-789');
        
        $this->assertEquals('success', $result->status);
        $this->assertStringContainsString('cancelled successfully', $result->message);
    }

    public function testCancelSubscriptionAlreadyCancelled(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=3&responsetext=No recurring subscriptions found'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $result = $this->gateway->cancelSubscription('nmi-sub-789');
        
        $this->assertEquals('success', $result->status);
        $this->assertTrue($result->alreadyCancelled);
        $this->assertStringContainsString('already cancelled', $result->message);
    }

    public function testCreatePlanSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=1&plan_id=nmi-plan-101&responsetext=Plan Added'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $planDto = new CreatePlanDto(
            planId: 'plan-123',
            planName: 'Premium Plan',
            amount: 29.99,
            frequency: 'monthly',
            dayFrequency: 30
        );
        
        $result = $this->gateway->createPlan($planDto);
        
        $this->assertEquals('success', $result->status);
        $this->assertEquals('plan-123', $result->planId);
        $this->assertStringContainsString('Plan Added', $result->message);
    }

    public function testCreatePlanFailed(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=3&responsetext=Duplicate plan name'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $planDto = new CreatePlanDto(
            planId: 'plan-123',
            planName: 'Existing Plan',
            amount: 29.99,
            frequency: 'monthly',
            dayFrequency: 30
        );
        
        $result = $this->gateway->createPlan($planDto);
        
        $this->assertEquals('error', $result->status);
        $this->assertStringContainsString('Duplicate plan name', $result->message);
    }

    public function testCreateCustomerVaultSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn(
            'response=1&customer_vault_id=vault-12345&responsetext=Customer Added'
        );
        $this->mockHttpClient->method('request')->willReturn($mockResponse);
        
        $billingInfo = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'address1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001'
        ];
        
        $result = $this->gateway->createCustomerVault($billingInfo);
        
        $this->assertEquals('success', $result->status);
        $this->assertEquals('vault-12345', $result->customerVaultId);
        $this->assertStringContainsString('Customer vault created successfully', $result->message);
    }

    public function testGatewayExceptionHandling(): void
    {
        $this->mockHttpClient
            ->method('request')
            ->willThrowException(new \Exception('Network connection failed'));
        
        $result = $this->gateway->processRebilling('sub-123', 'vault-456', Money::fromFloat(29.99, 'USD'));
        
        $this->assertEquals('error', $result->status);
        $this->assertStringContainsString('Network connection failed', $result->message);
    }
}