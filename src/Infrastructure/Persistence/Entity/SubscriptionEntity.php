<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Entity;

use App\Domain\Subscription\ValueObject\SubscriptionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

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

    #[ORM\ManyToOne(targetEntity: CustomerEntity::class)]
    #[ORM\JoinColumn(name: 'customer_vault_id', referencedColumnName: 'customer_vault_id', nullable: false)]
    private CustomerEntity $customer;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = 'active';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $nextChargeDate;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $customerVaultId = null;

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

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    // These methods delegate to the customer relationship for backward compatibility
    public function getCustomerEmail(): string
    {
        return $this->customer->getEmail();
    }

    public function getBillingFirstName(): string
    {
        return $this->customer->getFirstName();
    }

    public function getBillingLastName(): string
    {
        return $this->customer->getLastName();
    }

    public function getBillingStreet1(): string
    {
        return $this->customer->getStreet1();
    }

    public function getBillingStreet2(): ?string
    {
        return $this->customer->getStreet2();
    }

    public function getBillingCity(): string
    {
        return $this->customer->getCity();
    }

    public function getBillingState(): string
    {
        return $this->customer->getState();
    }

    public function getBillingPostalCode(): string
    {
        return $this->customer->getPostalCode();
    }

    public function getBillingCountry(): string
    {
        return $this->customer->getCountry();
    }

    public function getBillingPhone(): ?string
    {
        return $this->customer->getPhone();
    }

    // These fields are now retrieved from the related plan
    public function getAmount(): float
    {
        return $this->plan->getAmount();
    }

    public function getFrequency(): string
    {
        return $this->plan->getFrequency();
    }

    public function getCurrencyCode(): string
    {
        return $this->plan->getCurrencyCode();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, SubscriptionStatus::getAllValidStatuses(), true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid subscription status "%s". Allowed values are: %s',
                    $status,
                    implode(', ', SubscriptionStatus::getAllValidStatuses())
                )
            );
        }

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
