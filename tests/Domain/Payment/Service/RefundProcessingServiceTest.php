<?php

declare(strict_types=1);

namespace App\Tests\Domain\Payment\Service;

use App\Domain\Payment\Event\PaymentRefundedSuccessfullyEvent;
use App\Domain\Payment\Exception\InvalidRefundAmountException;
use App\Domain\Payment\Service\RefundProcessingService;
use App\Domain\Payment\ValueObject\PaymentStatus;
use App\Entity\PaymentTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RefundProcessingServiceTest extends TestCase
{
    private RefundProcessingService $service;

    protected function setUp(): void
    {
        $this->service = new RefundProcessingService();
    }

    public function testFullRefundProcessing(): void
    {
        // Arrange
        $originalTransaction = new PaymentTransaction();
        $originalTransaction->setAmount(100.00);
        $originalTransaction->setSubscriptionId('sub_12345');

        $refundEvent = new PaymentRefundedSuccessfullyEvent(
            originalTransactionId: 'txn_123',
            refundTransactionId: 'ref_456',
            refundAmount: 100.00,
            currency: 'USD',
            occurredOn: new DateTimeImmutable()
        );

        // Act
        $result = $this->service->processRefund($originalTransaction, $refundEvent);

        // Assert
        $this->assertEquals(PaymentStatus::refunded(), $result->newStatus);
        $this->assertTrue($result->shouldCancelSubscription);
        $this->assertFalse($result->isPartialRefund);
        $this->assertTrue($result->isFullRefund());
        $this->assertEquals(100.00, $result->originalAmount);
        $this->assertEquals(100.00, $result->refundAmount);
        $this->assertEquals(0.00, $result->getRemainingAmount());
        $this->assertEquals(1.0, $result->getRefundPercentage());
    }

    public function testPartialRefundProcessing(): void
    {
        // Arrange
        $originalTransaction = new PaymentTransaction();
        $originalTransaction->setAmount(100.00);
        $originalTransaction->setSubscriptionId('sub_12345');

        $refundEvent = new PaymentRefundedSuccessfullyEvent(
            originalTransactionId: 'txn_123',
            refundTransactionId: 'ref_456',
            refundAmount: 50.00,
            currency: 'USD',
            occurredOn: new DateTimeImmutable()
        );

        // Act
        $result = $this->service->processRefund($originalTransaction, $refundEvent);

        // Assert
        $this->assertEquals(PaymentStatus::partiallyRefunded(), $result->newStatus);
        $this->assertTrue($result->shouldCancelSubscription); // NEW BUSINESS RULE: Always cancel
        $this->assertTrue($result->isPartialRefund);
        $this->assertFalse($result->isFullRefund());
        $this->assertEquals(100.00, $result->originalAmount);
        $this->assertEquals(50.00, $result->refundAmount);
        $this->assertEquals(50.00, $result->getRemainingAmount());
        $this->assertEquals(0.5, $result->getRefundPercentage());
    }

    public function testOneTimePaymentRefund(): void
    {
        // Arrange
        $originalTransaction = new PaymentTransaction();
        $originalTransaction->setAmount(75.00);
        $originalTransaction->setSubscriptionId(null); // No subscription

        $refundEvent = new PaymentRefundedSuccessfullyEvent(
            originalTransactionId: 'txn_123',
            refundTransactionId: 'ref_456',
            refundAmount: 75.00,
            currency: 'USD',
            occurredOn: new DateTimeImmutable()
        );

        // Act
        $result = $this->service->processRefund($originalTransaction, $refundEvent);

        // Assert
        $this->assertEquals(PaymentStatus::refunded(), $result->newStatus);
        $this->assertFalse($result->shouldCancelSubscription); // No subscription to cancel
        $this->assertFalse($result->isPartialRefund);
        $this->assertTrue($result->isFullRefund());
        $this->assertEquals(75.00, $result->originalAmount);
        $this->assertEquals(75.00, $result->refundAmount);
        $this->assertEquals(0.00, $result->getRemainingAmount());
        $this->assertEquals(1.0, $result->getRefundPercentage());
    }

    public function testOverRefundValidation(): void
    {
        // Arrange
        $originalTransaction = new PaymentTransaction();
        $originalTransaction->setAmount(100.00);

        $refundEvent = new PaymentRefundedSuccessfullyEvent(
            originalTransactionId: 'txn_123',
            refundTransactionId: 'ref_456',
            refundAmount: 150.00, // More than original amount!
            currency: 'USD',
            occurredOn: new DateTimeImmutable()
        );

        // Act & Assert
        $this->expectException(InvalidRefundAmountException::class);
        $this->expectExceptionMessage('Refund amount ($150.00) cannot exceed original transaction amount ($100.00) for transaction txn_123');
        
        $this->service->processRefund($originalTransaction, $refundEvent);
    }

    public function testNegativeRefundValidation(): void
    {
        // Arrange
        $originalTransaction = new PaymentTransaction();
        $originalTransaction->setAmount(100.00);

        $refundEvent = new PaymentRefundedSuccessfullyEvent(
            originalTransactionId: 'txn_123',
            refundTransactionId: 'ref_456',
            refundAmount: -10.00, // Negative amount!
            currency: 'USD',
            occurredOn: new DateTimeImmutable()
        );

        // Act & Assert
        $this->expectException(InvalidRefundAmountException::class);
        $this->expectExceptionMessage('Refund amount must be greater than zero, got $-10.00 for transaction txn_123');
        
        $this->service->processRefund($originalTransaction, $refundEvent);
    }

    public function testZeroRefundValidation(): void
    {
        // Arrange
        $originalTransaction = new PaymentTransaction();
        $originalTransaction->setAmount(100.00);

        $refundEvent = new PaymentRefundedSuccessfullyEvent(
            originalTransactionId: 'txn_123',
            refundTransactionId: 'ref_456',
            refundAmount: 0.00, // Zero amount!
            currency: 'USD',
            occurredOn: new DateTimeImmutable()
        );

        // Act & Assert
        $this->expectException(InvalidRefundAmountException::class);
        $this->expectExceptionMessage('Refund amount must be greater than zero, got $0.00 for transaction txn_123');
        
        $this->service->processRefund($originalTransaction, $refundEvent);
    }

    public function testRefundPercentageCalculation(): void
    {
        // Arrange
        $originalTransaction = new PaymentTransaction();
        $originalTransaction->setAmount(200.00);

        $refundEvent = new PaymentRefundedSuccessfullyEvent(
            originalTransactionId: 'txn_123',
            refundTransactionId: 'ref_456',
            refundAmount: 75.00, // 37.5% of original
            currency: 'USD',
            occurredOn: new DateTimeImmutable()
        );

        // Act
        $result = $this->service->processRefund($originalTransaction, $refundEvent);

        // Assert
        $this->assertEquals(0.375, $result->getRefundPercentage());
        $this->assertEquals(125.00, $result->getRemainingAmount());
    }
}
