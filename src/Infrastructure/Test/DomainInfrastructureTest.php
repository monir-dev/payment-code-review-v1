<?php

declare(strict_types=1);

namespace App\Infrastructure\Test;

use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\ValueObject\PaymentStatus;
use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Shared\ValueObject\Money;
use App\Infrastructure\Persistence\PaymentEntityMapper;
use PHPUnit\Framework\TestCase;

/**
 * Integration test to verify domain-infrastructure mapping works correctly
 */
class DomainInfrastructureTest extends TestCase
{
    private PaymentEntityMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PaymentEntityMapper();
    }

    public function testPaymentEntityMapping(): void
    {
        // Create domain objects
        $transactionId = TransactionId::fromString('test_txn_123');
        $money = Money::fromFloat(25.99, 'USD');
        $email = Email::fromString('test@example.com');
        $address = Address::create('123 Main St', null, 'Anytown', 'NY', '12345', 'USA');
        $billingInfo = new BillingInformation('John', 'Doe', $email, $address, '555-1234');

        // Create domain entity
        $payment = Payment::create($transactionId, $money, $billingInfo, 'token_123', '4321');

        // Test domain business logic
        $payment->approve();
        $this->assertTrue($payment->getStatus()->isApproved());

        // Test mapping to infrastructure entity
        $entity = $this->mapper->toEntity($payment);
        $this->assertEquals('test_txn_123', $entity->getTransactionId());
        $this->assertEquals(25.99, $entity->getAmount());
        $this->assertEquals('USD', $entity->getCurrencyCode());
        $this->assertEquals('approved', $entity->getPaymentStatus());
        $this->assertEquals('John', $entity->getBillingFirstName());
        $this->assertEquals('test@example.com', $entity->getBillingEmail());

        // Test mapping back to domain
        $reconstructedPayment = $this->mapper->toDomain($entity);
        $this->assertTrue($reconstructedPayment->getTransactionId()->equals($transactionId));
        $this->assertTrue($reconstructedPayment->getAmount()->equals($money));
        $this->assertTrue($reconstructedPayment->getStatus()->isApproved());
        $this->assertEquals('John', $reconstructedPayment->getBillingInformation()->getFirstName());
    }

    public function testValueObjectValidation(): void
    {
        // Test Money validation
        $this->expectException(\InvalidArgumentException::class);
        Money::fromFloat(-10.0, 'USD');
    }

    public function testEmailValidation(): void
    {
        // Test Email validation
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString('invalid-email');
    }

    public function testTransactionIdGeneration(): void
    {
        $id1 = TransactionId::generate();
        $id2 = TransactionId::generate();

        $this->assertNotEquals($id1->getValue(), $id2->getValue());
        $this->assertStringStartsWith('txn_', $id1->getValue());
    }

    public function testMoneyOperations(): void
    {
        $money1 = Money::fromFloat(10.0, 'USD');
        $money2 = Money::fromFloat(5.0, 'USD');

        $sum = $money1->add($money2);
        $this->assertEquals(15.0, $sum->getAmount());

        $difference = $money1->subtract($money2);
        $this->assertEquals(5.0, $difference->getAmount());

        $doubled = $money1->multiply(2.0);
        $this->assertEquals(20.0, $doubled->getAmount());
    }

    public function testPaymentStatusTransitions(): void
    {
        $payment = $this->createTestPayment();

        // Test valid transition
        $payment->approve();
        $this->assertTrue($payment->getStatus()->isApproved());

        // Test business rule enforcement
        $this->expectException(\DomainException::class);
        $payment->approve(); // Cannot approve already approved payment
    }

    private function createTestPayment(): Payment
    {
        $transactionId = TransactionId::generate();
        $money = Money::fromFloat(10.0, 'USD');
        $email = Email::fromString('test@example.com');
        $address = Address::create('123 Test St', null, 'Test City', 'TS', '12345', 'USA');
        $billingInfo = new BillingInformation('Test', 'User', $email, $address);

        return Payment::create($transactionId, $money, $billingInfo);
    }
}
