<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObject;

use InvalidArgumentException;

final class PlanStatus
{
    private const ACTIVE = 'active';
    private const INACTIVE = 'inactive';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::INACTIVE,
    ];

    public function __construct(
        private readonly string $status
    ) {
        if (!in_array($status, self::VALID_STATUSES)) {
            throw new InvalidArgumentException(sprintf('Invalid plan status: %s', $status));
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
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

    public function isInactive(): bool
    {
        return $this->status === self::INACTIVE;
    }

    public function equals(PlanStatus $other): bool
    {
        return $this->status === $other->status;
    }

    public function __toString(): string
    {
        return $this->status;
    }
}
