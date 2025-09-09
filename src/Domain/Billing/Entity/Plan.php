<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entity;

use App\Domain\Billing\Event\PlanActivatedEvent;
use App\Domain\Billing\Event\PlanCreatedEvent;
use App\Domain\Billing\Event\PlanDeactivatedEvent;
use App\Domain\Billing\ValueObject\PlanId;
use App\Domain\Billing\ValueObject\PlanStatus;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Subscription\ValueObject\BillingCycle;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Plan
{
    private array $domainEvents = [];

    public function __construct(
        private readonly PlanId $planId,
        private readonly string $name,
        private readonly Money $amount,
        private readonly BillingCycle $billingCycle,
        private PlanStatus $status,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Plan name cannot be empty');
        }
        if ($amount->isZero()) {
            throw new InvalidArgumentException('Plan amount cannot be zero');
        }
    }

    public static function create(
        PlanId $planId,
        string $name,
        Money $amount,
        BillingCycle $billingCycle
    ): self {
        $plan = new self(
            $planId,
            $name,
            $amount,
            $billingCycle,
            PlanStatus::active()
        );

        $plan->recordEvent(new PlanCreatedEvent($planId, $name, $amount, $billingCycle));

        return $plan;
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            throw new DomainException('Plan is already active');
        }

        $this->status = PlanStatus::active();
        $this->recordEvent(new PlanActivatedEvent($this->planId));
    }

    public function deactivate(): void
    {
        if ($this->status->isInactive()) {
            throw new DomainException('Plan is already inactive');
        }

        $this->status = PlanStatus::inactive();
        $this->recordEvent(new PlanDeactivatedEvent($this->planId));
    }

    public function getPlanId(): PlanId
    {
        return $this->planId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getBillingCycle(): BillingCycle
    {
        return $this->billingCycle;
    }

    public function getStatus(): PlanStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isAvailableForSubscription(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return array<object>
     */
    public function getUncommittedEvents(): array
    {
        return $this->domainEvents;
    }

    public function markEventsAsCommitted(): void
    {
        $this->domainEvents = [];
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
