<?php

declare(strict_types=1);

namespace App\Domain\Payment\ValueObject;

use InvalidArgumentException;

final class TransactionId
{
    public function __construct(
        private readonly string $value
    ) {
        if (empty(trim($value))) {
            throw new InvalidArgumentException('Transaction ID cannot be empty');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function generate(): self
    {
        return new self('txn_' . uniqid() . '_' . random_int(1000, 9999));
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(TransactionId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
