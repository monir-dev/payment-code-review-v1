<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
class SubscriptionEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $subscriptionId;

    #[ORM\ManyToOne(targetEntity: PlanEntity::class)]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: false)]
    private PlanEntity $plan;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $customerEmail;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingFirstName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingLastName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingStreet1;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $billingStreet2 = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingCity;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingState;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $billingPostalCode;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingCountry;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $billingPhone = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $amount;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $frequency;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $currencyCode = 'USD';

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = 'active';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $nextChargeDate;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $customerVaultId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $originalTransactionId = null;

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
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(string $subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
    }

    public function getPlan(): PlanEntity
    {
        return $this->plan;
    }

    public function setPlan(PlanEntity $plan): void
    {
        $this->plan = $plan;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    public function getBillingFirstName(): string
    {
        return $this->billingFirstName;
    }

    public function setBillingFirstName(string $billingFirstName): void
    {
        $this->billingFirstName = $billingFirstName;
    }

    public function getBillingLastName(): string
    {
        return $this->billingLastName;
    }

    public function setBillingLastName(string $billingLastName): void
    {
        $this->billingLastName = $billingLastName;
    }

    public function getBillingStreet1(): string
    {
        return $this->billingStreet1;
    }

    public function setBillingStreet1(string $billingStreet1): void
    {
        $this->billingStreet1 = $billingStreet1;
    }

    public function getBillingStreet2(): ?string
    {
        return $this->billingStreet2;
    }

    public function setBillingStreet2(?string $billingStreet2): void
    {
        $this->billingStreet2 = $billingStreet2;
    }

    public function getBillingCity(): string
    {
        return $this->billingCity;
    }

    public function setBillingCity(string $billingCity): void
    {
        $this->billingCity = $billingCity;
    }

    public function getBillingState(): string
    {
        return $this->billingState;
    }

    public function setBillingState(string $billingState): void
    {
        $this->billingState = $billingState;
    }

    public function getBillingPostalCode(): string
    {
        return $this->billingPostalCode;
    }

    public function setBillingPostalCode(string $billingPostalCode): void
    {
        $this->billingPostalCode = $billingPostalCode;
    }

    public function getBillingCountry(): string
    {
        return $this->billingCountry;
    }

    public function setBillingCountry(string $billingCountry): void
    {
        $this->billingCountry = $billingCountry;
    }

    public function getBillingPhone(): ?string
    {
        return $this->billingPhone;
    }

    public function setBillingPhone(?string $billingPhone): void
    {
        $this->billingPhone = $billingPhone;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): void
    {
        $this->frequency = $frequency;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getNextChargeDate(): \DateTimeImmutable
    {
        return $this->nextChargeDate;
    }

    public function setNextChargeDate(\DateTimeImmutable $nextChargeDate): void
    {
        $this->nextChargeDate = $nextChargeDate;
    }

    public function getCustomerVaultId(): ?string
    {
        return $this->customerVaultId;
    }

    public function setCustomerVaultId(?string $customerVaultId): void
    {
        $this->customerVaultId = $customerVaultId;
    }

    public function getOriginalTransactionId(): ?string
    {
        return $this->originalTransactionId;
    }

    public function setOriginalTransactionId(?string $originalTransactionId): void
    {
        $this->originalTransactionId = $originalTransactionId;
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
}
