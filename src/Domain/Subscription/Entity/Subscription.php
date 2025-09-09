<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Entity;

use App\Domain\Billing\Entity\Plan;
use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Subscription\Event\SubscriptionCancelledEvent;
use App\Domain\Subscription\Event\SubscriptionCreatedEvent;
use App\Domain\Subscription\Event\SubscriptionRebilledEvent;
use App\Domain\Subscription\ValueObject\BillingCycle;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use App\Domain\Subscription\ValueObject\SubscriptionStatus;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Subscription
{
    private array $domainEvents = [];

    public function __construct(
        private readonly SubscriptionId $subscriptionId,
        private readonly Plan $plan,
        private readonly BillingInformation $billingInformation,
        private SubscriptionStatus $status,
        private readonly DateTimeImmutable $startDate,
        private DateTimeImmutable $nextChargeDate,
        private readonly ?string $customerVaultId = null,
        private readonly ?TransactionId $originalTransactionId = null,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {
        if ($startDate->getTimestamp() > $nextChargeDate->getTimestamp()) {
            throw new InvalidArgumentException('Start date cannot be after next charge date');
        }
    }

    public static function create(
        SubscriptionId $subscriptionId,
        Plan $plan,
        BillingInformation $billingInformation,
        DateTimeImmutable $startDate,
        ?string $customerVaultId = null,
        ?TransactionId $originalTransactionId = null
    ): self {
        $nextChargeDate = $plan->getBillingCycle()->calculateNextChargeDate($startDate);

        $subscription = new self(
            $subscriptionId,
            $plan,
            $billingInformation,
            SubscriptionStatus::active(),
            $startDate,
            $nextChargeDate,
            $customerVaultId,
            $originalTransactionId
        );

        $subscription->recordEvent(new SubscriptionCreatedEvent(
            $subscriptionId,
            $plan->getPlanId(),
            $billingInformation->getEmail(),
            $plan->getAmount(),
            $startDate
        ));

        return $subscription;
    }

    public function cancel(string $reason = 'Customer request'): void
    {
        if (!$this->status->canBeCancelled()) {
            throw new DomainException(
                sprintf(
                    'Subscription cannot be cancelled. Current status is "%s" but only "active" or "paused" subscriptions can be cancelled.',
                    $this->status->getValue()
                )
            );
        }

        $this->status = SubscriptionStatus::cancelled();
        $this->recordEvent(new SubscriptionCancelledEvent($this->subscriptionId, $reason));
    }

    public function pause(): void
    {
        if (!$this->status->isActive()) {
            throw new DomainException('Only active subscriptions can be paused');
        }

        $this->status = SubscriptionStatus::paused();
    }

    public function processRebill(Money $amount, string $transactionId, string $reason = 'Manual rebill', ?DateTimeImmutable $processedAt = null): void
    {
        if (!$this->status->isActive()) {
            throw new DomainException(
                sprintf(
                    'Only active subscriptions can be rebilled. Current status is "%s".',
                    $this->status->getValue()
                )
            );
        }

        $processedAt = $processedAt ?? new DateTimeImmutable();

        // Calculate next charge date using the billing cycle
        $this->nextChargeDate = $this->plan->getBillingCycle()->calculateNextChargeDate($processedAt);

        // Record the rebill event
        $this->recordEvent(new SubscriptionRebilledEvent(
            $this->subscriptionId,
            $amount,
            $transactionId,
            $reason,
            $this->nextChargeDate
        ));
    }

    public function isDue(DateTimeImmutable $currentDate): bool
    {
        return $this->status->isActive()
            && $currentDate->getTimestamp() >= $this->nextChargeDate->getTimestamp();
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function getBillingInformation(): BillingInformation
    {
        return $this->billingInformation;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getNextChargeDate(): DateTimeImmutable
    {
        return $this->nextChargeDate;
    }

    public function getCustomerVaultId(): ?string
    {
        return $this->customerVaultId;
    }

    public function getOriginalTransactionId(): ?TransactionId
    {
        return $this->originalTransactionId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAmount(): Money
    {
        return $this->plan->getAmount();
    }

    public function getBillingCycle(): BillingCycle
    {
        return $this->plan->getBillingCycle();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
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
