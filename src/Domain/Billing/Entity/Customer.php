<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entity;

use App\Domain\Shared\ValueObject\Email;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\BillingInformation;

final class Customer
{
    private string $customerVaultId;
    private Email $email;
    private string $firstName;
    private string $lastName;
    private Address $address;
    private ?string $phone;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $customerVaultId,
        Email $email,
        string $firstName,
        string $lastName,
        Address $address,
        ?string $phone = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->customerVaultId = $customerVaultId;
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->address = $address;
        $this->phone = $phone;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public static function create(
        string $customerVaultId,
        string $email,
        string $firstName,
        string $lastName,
        string $street1,
        ?string $street2,
        string $city,
        string $state,
        string $postalCode,
        string $country,
        ?string $phone = null
    ): self {
        return new self(
            $customerVaultId,
            Email::fromString($email),
            $firstName,
            $lastName,
            Address::create($street1, $street2, $city, $state, $postalCode, $country),
            $phone
        );
    }

    public function getCustomerVaultId(): string
    {
        return $this->customerVaultId;
    }

    public function getEmail(): Email
    {
        return $this->email;
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
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getBillingInformation(): BillingInformation
    {
        return new BillingInformation(
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->address,
            $this->phone
        );
    }
}
