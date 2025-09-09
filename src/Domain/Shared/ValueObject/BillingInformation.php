<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

final class BillingInformation
{
    public function __construct(
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly Email $email,
        private readonly Address $address,
        private readonly ?string $phone = null
    ) {
        if (empty(trim($firstName))) {
            throw new InvalidArgumentException('First name cannot be empty');
        }
        if (empty(trim($lastName))) {
            throw new InvalidArgumentException('Last name cannot be empty');
        }
        if ($phone !== null && empty(trim($phone))) {
            throw new InvalidArgumentException('Phone cannot be empty when provided');
        }
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function equals(BillingInformation $other): bool
    {
        return $this->firstName === $other->firstName
            && $this->lastName === $other->lastName
            && $this->email->equals($other->email)
            && $this->address->equals($other->address)
            && $this->phone === $other->phone;
    }
}
