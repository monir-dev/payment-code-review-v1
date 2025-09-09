<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

final class Address
{
    public function __construct(
        private readonly string $street1,
        private readonly ?string $street2,
        private readonly string $city,
        private readonly string $state,
        private readonly string $postalCode,
        private readonly string $country
    ) {
        if (empty(trim($street1))) {
            throw new InvalidArgumentException('Street address cannot be empty');
        }
        if (empty(trim($city))) {
            throw new InvalidArgumentException('City cannot be empty');
        }
        if (empty(trim($state))) {
            throw new InvalidArgumentException('State cannot be empty');
        }
        if (empty(trim($postalCode))) {
            throw new InvalidArgumentException('Postal code cannot be empty');
        }
        if (empty(trim($country))) {
            throw new InvalidArgumentException('Country cannot be empty');
        }
    }

    public static function create(
        string $street1,
        ?string $street2,
        string $city,
        string $state,
        string $postalCode,
        string $country
    ): self {
        return new self($street1, $street2, $city, $state, $postalCode, $country);
    }

    public function getStreet1(): string
    {
        return $this->street1;
    }

    public function getStreet2(): ?string
    {
        return $this->street2;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getFullAddress(): string
    {
        $address = $this->street1;
        if ($this->street2) {
            $address .= ', ' . $this->street2;
        }
        return $address . ', ' . $this->city . ', ' . $this->state . ' ' . $this->postalCode . ', ' . $this->country;
    }

    public function equals(Address $other): bool
    {
        return $this->street1 === $other->street1
            && $this->street2 === $other->street2
            && $this->city === $other->city
            && $this->state === $other->state
            && $this->postalCode === $other->postalCode
            && $this->country === $other->country;
    }
}
