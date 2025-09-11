<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Handler\InitializePaymentCommandHandler;
use App\Application\Command\InitializePaymentCommand;
use App\Application\Service\PaymentGatewayInterface;
use App\Application\Response\Gateway\InitializePaymentResponse;
use App\Domain\Shared\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class InitializePaymentCommandHandlerTest extends TestCase
{
    public function testSuccessfulPaymentInitialization(): void
    {
        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);
        
        $mockGateway
            ->method('initializePayment')
            ->willReturn(new InitializePaymentResponse(
                status: 'success',
                formUrl: 'https://gateway.test/form/token123',
                tokenId: 'token123',
                message: 'Success'
            ));

        $handler = new InitializePaymentCommandHandler($mockGateway, $mockLogger);
        
        $command = new InitializePaymentCommand(
            amount: Money::fromFloat(29.99, 'USD'),
            currency: 'USD',
            redirectUrl: 'http://localhost/success',
            billingFirstName: 'John',
            billingLastName: 'Doe',
            billingEmail: 'john@example.com',
            billingAddress1: '123 Main St',
            billingAddress2: null,
            billingCity: 'New York',
            billingState: 'NY',
            billingPostal: '10001',
            billingCountry: 'US',
            billingPhone: null
        );

        $result = $handler->handle($command);
        
        $this->assertEquals('success', $result->status);
        $this->assertEquals('https://gateway.test/form/token123', $result->redirectUrl);
    }

    public function testFailedPaymentInitialization(): void
    {
        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);
        
        $mockGateway
            ->method('initializePayment')
            ->willThrowException(new \Exception('Gateway connection failed'));

        $handler = new InitializePaymentCommandHandler($mockGateway, $mockLogger);
        
        $command = new InitializePaymentCommand(
            amount: Money::fromFloat(29.99, 'USD'),
            currency: 'USD',
            redirectUrl: 'http://localhost/success',
            billingFirstName: 'John',
            billingLastName: 'Doe',
            billingEmail: 'john@example.com',
            billingAddress1: '123 Main St',
            billingAddress2: null,
            billingCity: 'New York',
            billingState: 'NY',
            billingPostal: '10001',
            billingCountry: 'US',
            billingPhone: null
        );

        $result = $handler->handle($command);
        
        $this->assertEquals('error', $result->status);
        $this->assertStringContainsString('Gateway connection failed', $result->message);
    }
}