<?php

declare(strict_types=1);

namespace App\Tests\Domain\Subscription\ValueObject;

use App\Domain\Subscription\ValueObject\SubscriptionStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SubscriptionStatusTest extends TestCase
{
    public function testActiveStatus(): void
    {
        $status = SubscriptionStatus::active();

        $this->assertEquals('active', $status->getValue());
        $this->assertTrue($status->isActive());
        $this->assertFalse($status->isCancelled());
        $this->assertTrue($status->canBeCancelled());
        $this->assertEquals('active', (string) $status);
    }

    public function testCancelledStatus(): void
    {
        $status = SubscriptionStatus::cancelled();

        $this->assertEquals('cancelled', $status->getValue());
        $this->assertFalse($status->isActive());
        $this->assertTrue($status->isCancelled());
        $this->assertFalse($status->canBeCancelled());
        $this->assertEquals('cancelled', (string) $status);
    }

    public function testFromStringActive(): void
    {
        $status = SubscriptionStatus::fromString('active');

        $this->assertEquals('active', $status->getValue());
        $this->assertTrue($status->isActive());
    }

    public function testFromStringCancelled(): void
    {
        $status = SubscriptionStatus::fromString('cancelled');

        $this->assertEquals('cancelled', $status->getValue());
        $this->assertTrue($status->isCancelled());
    }

    public function testInvalidStatusThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid subscription status: invalid_status');

        SubscriptionStatus::fromString('invalid_status');
    }

    public function testEquals(): void
    {
        $status1 = SubscriptionStatus::active();
        $status2 = SubscriptionStatus::active();
        $status3 = SubscriptionStatus::cancelled();

        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    public function testOnlyActiveCanBeCancelled(): void
    {
        $activeStatus = SubscriptionStatus::active();
        $cancelledStatus = SubscriptionStatus::cancelled();

        $this->assertTrue($activeStatus->canBeCancelled());
        $this->assertFalse($cancelledStatus->canBeCancelled());
    }
}
