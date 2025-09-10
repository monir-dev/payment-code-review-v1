<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payment_transactions_tbl')]
class PaymentEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $transactionId;

    #[ORM\Column(type: Types::FLOAT)]
    private float $amount;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currencyCode;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $paymentStatus;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingFirstName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingLastName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $billingEmail;

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

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $gatewayToken = null;

    #[ORM\Column(type: Types::STRING, length: 4, nullable: true)]
    private ?string $last4Digits = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): void
    {
        $this->paymentStatus = $paymentStatus;
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

    public function getBillingEmail(): string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(string $billingEmail): void
    {
        $this->billingEmail = $billingEmail;
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

    public function getGatewayToken(): ?string
    {
        return $this->gatewayToken;
    }

    public function setGatewayToken(?string $gatewayToken): void
    {
        $this->gatewayToken = $gatewayToken;
    }

    public function getLast4Digits(): ?string
    {
        return $this->last4Digits;
    }

    public function setLast4Digits(?string $last4Digits): void
    {
        $this->last4Digits = $last4Digits;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
