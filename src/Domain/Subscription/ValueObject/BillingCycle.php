<?php

declare(strict_types=1);

namespace App\Domain\Subscription\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

final class BillingCycle
{
    private const WEEKLY = 'weekly';
    private const MONTHLY = 'monthly';
    private const YEARLY = 'yearly';

    private const VALID_FREQUENCIES = [
        self::WEEKLY => 7,
        self::MONTHLY => 30,
        self::YEARLY => 365,
    ];

    public function __construct(
        private readonly string $frequency,
        private readonly int $dayFrequency
    ) {
        if (!array_key_exists($frequency, self::VALID_FREQUENCIES)) {
            throw new InvalidArgumentException(sprintf('Invalid billing frequency: %s', $frequency));
        }
        
        if ($dayFrequency <= 0) {
            throw new InvalidArgumentException('Day frequency must be positive');
        }
    }

    public static function weekly(): self
    {
        return new self(self::WEEKLY, 7);
    }

    public static function monthly(): self
    {
        return new self(self::MONTHLY, 30);
    }

    public static function yearly(): self
    {
        return new self(self::YEARLY, 365);
    }

    public static function fromFrequency(string $frequency): self
    {
        $frequency = strtolower($frequency);
        
        if (!array_key_exists($frequency, self::VALID_FREQUENCIES)) {
            throw new InvalidArgumentException(sprintf('Invalid billing frequency: %s', $frequency));
        }
        
        return new self($frequency, self::VALID_FREQUENCIES[$frequency]);
    }

    public static function custom(string $frequency, int $dayFrequency): self
    {
        return new self($frequency, $dayFrequency);
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function getDayFrequency(): int
    {
        return $this->dayFrequency;
    }

    public function calculateNextChargeDate(DateTimeImmutable $currentDate): DateTimeImmutable
    {
        return $currentDate->modify('+' . $this->dayFrequency . ' days');
    }

    public function isWeekly(): bool
    {
        return $this->frequency === self::WEEKLY;
    }

    public function isMonthly(): bool
    {
        return $this->frequency === self::MONTHLY;
    }

    public function isYearly(): bool
    {
        return $this->frequency === self::YEARLY;
    }

    public function equals(BillingCycle $other): bool
    {
        return $this->frequency === $other->frequency 
            && $this->dayFrequency === $other->dayFrequency;
    }

    public function __toString(): string
    {
        return $this->frequency . ' (' . $this->dayFrequency . ' days)';
    }
}
