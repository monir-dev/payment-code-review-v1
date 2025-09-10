<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customers')]
class CustomerEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $customerVaultId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $firstName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $lastName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $street1;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $street2 = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $city;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $state;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $postalCode;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $country;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    // Getters and setters
    public function getCustomerVaultId(): string
    {
        return $this->customerVaultId;
    }

    public function setCustomerVaultId(string $customerVaultId): void
    {
        $this->customerVaultId = $customerVaultId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getStreet1(): string
    {
        return $this->street1;
    }

    public function setStreet1(string $street1): void
    {
        $this->street1 = $street1;
    }

    public function getStreet2(): ?string
    {
        return $this->street2;
    }

    public function setStreet2(?string $street2): void
    {
        $this->street2 = $street2;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function __toString(): string
    {
        return sprintf(
            'Customer[customerVaultId=%s, email=%s, name=%s %s]',
            $this->customerVaultId ?? 'null',
            $this->email ?? 'null',
            $this->firstName ?? '',
            $this->lastName ?? ''
        );
    }
}
