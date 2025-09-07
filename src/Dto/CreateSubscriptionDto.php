<?php

namespace App\Dto;

use App\Entity\Plan;

final class CreateSubscriptionDto
{
    public readonly Plan $plan;
    public readonly \DateTime $startDate;
    public readonly string $customerEmail;
    public readonly string $billingFirstName;
    public readonly string $billingLastName;
    public readonly ?string $billingAddress1;
    public readonly ?string $billingAddress2;
    public readonly ?string $billingCity;
    public readonly ?string $billingState;
    public readonly string $billingPostal;
    public readonly string $billingCountry;
    public readonly ?string $billingPhone;

    public function __construct(
        Plan $plan,
        \DateTime $startDate,
        string $customerEmail,
        string $billingFirstName,
        string $billingLastName,
        ?string $billingAddress1,
        ?string $billingAddress2,
        ?string $billingCity,
        ?string $billingState,
        string $billingPostal,
        string $billingCountry,
        ?string $billingPhone
    ) {
        $this->plan = $plan;
        $this->startDate = $startDate;
        $this->customerEmail = $customerEmail;
        $this->billingFirstName = $billingFirstName;
        $this->billingLastName = $billingLastName;
        $this->billingAddress1 = $billingAddress1;
        $this->billingAddress2 = $billingAddress2;
        $this->billingCity = $billingCity;
        $this->billingState = $billingState;
        $this->billingPostal = $billingPostal;
        $this->billingCountry = $billingCountry;
        $this->billingPhone = $billingPhone;
    }

    public static function fromFormData(array $formData): self
    {
        return new self(
            $formData['plan'],
            $formData['start_date'],
            $formData['customer_email'],
            $formData['billingFirstName'],
            $formData['billingLastName'],
            $formData['billingAddress1'] ?? null,
            $formData['billingAddress2'] ?? null,
            $formData['billingCity'] ?? null,
            $formData['billingState'] ?? null,
            $formData['billingPostal'],
            $formData['billingCountry'],
            $formData['billingPhone'] ?? null
        );
    }

    /**
     * Convert to array for repository operations
     */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
            'start_date' => $this->startDate,
            'customer_email' => $this->customerEmail,
            'billing_first_name' => $this->billingFirstName,
            'billing_last_name' => $this->billingLastName,
            'billing_address1' => $this->billingAddress1,
            'billing_address2' => $this->billingAddress2,
            'billing_city' => $this->billingCity,
            'billing_state' => $this->billingState,
            'billing_postal' => $this->billingPostal,
            'billing_country' => $this->billingCountry,
            'billing_phone' => $this->billingPhone,
        ];
    }

    public function getNextChargeDate(): \DateTime
    {
        $nextChargeDate = clone $this->startDate;

        return match($this->plan->getFrequency()) {
            'weekly' => $nextChargeDate->add(new \DateInterval('P7D')),
            'monthly' => $nextChargeDate->add(new \DateInterval('P1M')),
            'yearly' => $nextChargeDate->add(new \DateInterval('P1Y')),
            default => $nextChargeDate->add(new \DateInterval('P1M'))
        };
    }

    public function getAmount(): float
    {
        return $this->plan->getAmount();
    }

    public function getFrequency(): string
    {
        return $this->plan->getFrequency();
    }

    public function getPlanId(): string
    {
        return $this->plan->getPlanId();
    }
}
