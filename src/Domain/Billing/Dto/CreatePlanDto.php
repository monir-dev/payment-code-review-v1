<?php

declare(strict_types=1);

namespace App\Domain\Billing\Dto;

final class CreatePlanDto
{
    public readonly string $planId;
    public readonly string $planName;
    public readonly float $amount;
    public readonly string $frequency;
    public readonly int $dayFrequency;

    public function __construct(
        string $planId,
        string $planName,
        float $amount,
        string $frequency,
        int $dayFrequency
    ) {
        $this->planId = $planId;
        $this->planName = $planName;
        $this->amount = $amount;
        $this->frequency = $frequency;
        $this->dayFrequency = $dayFrequency;
    }

    /**
     * Create DTO from form data with generated plan ID and day frequency
     */
    public static function fromFormData(array $formData): self
    {
        $amount = (float) $formData['amount'];
        $frequency = $formData['frequency'];
        $planName = $formData['planName'];

        $planId = 'PLAN_' . strtoupper($frequency) . '_' . number_format($amount, 2, '', '') . '_' . date('YmdHis');

        $dayFrequency = match($frequency) {
            'weekly' => 7,
            'monthly' => 30,
            'yearly' => 365,
            default => 30
        };

        return new self($planId, $planName, $amount, $frequency, $dayFrequency);
    }

    public function toArray(): array
    {
        return [
            'plan_id' => $this->planId,
            'plan_name' => $this->planName,
            'amount' => $this->amount,
            'frequency' => $this->frequency,
            'day_frequency' => $this->dayFrequency,
        ];
    }
}
