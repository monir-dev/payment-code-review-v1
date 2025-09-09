<?php

declare(strict_types=1);

namespace App\Domain\Subscription\ValueObject;

use InvalidArgumentException;

final class SubscriptionStatus
{
    private const ACTIVE = 'active';
    private const PAUSED = 'paused';
    private const CANCELLED = 'cancelled';
    private const EXPIRED = 'expired';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::PAUSED,
        self::CANCELLED,
        self::EXPIRED,
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

    public static function paused(): self
    {
        return new self(self::PAUSED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
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

    public function isPaused(): bool
    {
        return $this->status === self::PAUSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::EXPIRED;
    }

    public function canBeCancelled(): bool
    {
        return $this->isActive() || $this->isPaused();
    }

    public function canBeReactivated(): bool
    {
        return $this->isPaused();
    }

    public function equals(SubscriptionStatus $other): bool
    {
        return $this->status === $other->status;
    }

    public function __toString(): string
    {
        return $this->status;
    }
}
