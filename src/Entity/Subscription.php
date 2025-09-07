<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;


    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Plan $plan = null;

    #[ORM\Column(length: 255)]
    private ?string $subscription_id = null;

    #[ORM\Column(length: 255)]
    private ?string $customer_vault_id = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $amount = null;

    #[ORM\Column(length: 50)]
    private ?string $currency_code = 'USD';

    #[ORM\Column(length: 50)]
    private ?string $frequency = null; // monthly, weekly, yearly

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $start_date = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $next_charge_date = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'active'; // active, paused, cancelled

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customer_email = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $last4_digits = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $updated_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }


    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getSubscriptionId(): ?string
    {
        return $this->subscription_id;
    }

    public function setSubscriptionId(string $subscription_id): static
    {
        $this->subscription_id = $subscription_id;
        return $this;
    }

    public function getCustomerVaultId(): ?string
    {
        return $this->customer_vault_id;
    }

    public function setCustomerVaultId(?string $customer_vault_id): static
    {
        $this->customer_vault_id = $customer_vault_id;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currency_code;
    }

    public function setCurrencyCode(string $currency_code): static
    {
        $this->currency_code = $currency_code;
        return $this;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->start_date;
    }

    public function setStartDate(\DateTime $start_date): static
    {
        $this->start_date = $start_date;
        return $this;
    }

    public function getNextChargeDate(): ?\DateTime
    {
        return $this->next_charge_date;
    }

    public function setNextChargeDate(?\DateTime $next_charge_date): static
    {
        $this->next_charge_date = $next_charge_date;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customer_email;
    }

    public function setCustomerEmail(?string $customer_email): static
    {
        $this->customer_email = $customer_email;
        return $this;
    }

    public function getLast4Digits(): ?string
    {
        return $this->last4_digits;
    }

    public function setLast4Digits(?string $last4_digits): static
    {
        $this->last4_digits = $last4_digits;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTime $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }
}
