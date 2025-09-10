<?php

declare(strict_types=1);

namespace App\Domain\Payment\ValueObject;

use InvalidArgumentException;

final class PaymentStatus
{
    private const APPROVED = 'approved';
    private const REFUNDED = 'refunded';
    private const PARTIALLY_REFUNDED = 'partially_refunded';

    private const VALID_STATUSES = [
        self::APPROVED,
        self::REFUNDED,
        self::PARTIALLY_REFUNDED,
    ];

    public function __construct(
        private readonly string $status
    ) {
        if (!in_array($status, self::VALID_STATUSES)) {
            throw new InvalidArgumentException(sprintf('Invalid payment status: %s', $status));
        }
    }

    public static function approved(): self
    {
        return new self(self::APPROVED);
    }


    public static function refunded(): self
    {
        return new self(self::REFUNDED);
    }

    public static function partiallyRefunded(): self
    {
        return new self(self::PARTIALLY_REFUNDED);
    }

    public static function fromString(string $status): self
    {
        return new self(strtolower($status));
    }

    public function getValue(): string
    {
        return $this->status;
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::REFUNDED;
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->status === self::PARTIALLY_REFUNDED;
    }

    public function canBeRefunded(): bool
    {
        return $this->isApproved() || $this->isPartiallyRefunded();
    }

    public function equals(PaymentStatus $other): bool
    {
        return $this->status === $other->status;
    }

    public function __toString(): string
    {
        return $this->status;
    }

    public static function getAllValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }
}
