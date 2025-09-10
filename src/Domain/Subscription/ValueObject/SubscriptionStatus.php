<?php

declare(strict_types=1);

namespace App\Domain\Subscription\ValueObject;

use InvalidArgumentException;

final class SubscriptionStatus
{
    private const ACTIVE = 'active';
    private const CANCELLED = 'cancelled';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::CANCELLED,
    ];

    public function __construct(
        private readonly string $status
    ) {
        if (!in_array($status, self::VALID_STATUSES)) {
            throw new InvalidArgumentException(sprintf('Invalid subscription status: %s', $status));
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function fromString(string $status): self
    {
        return new self(strtolower($status));
    }

    public function getValue(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    public function canBeCancelled(): bool
    {
        return $this->isActive();
    }

    public function equals(SubscriptionStatus $other): bool
    {
        return $this->status === $other->status;
    }

    /**
     * Get all valid subscription status values
     */
    public static function getAllValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }

    public function __toString(): string
    {
        return $this->status;
    }
}
