<?php

declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use App\Domain\Payment\Event\PaymentApprovedEvent;
use App\Domain\Payment\Event\PaymentDeclinedEvent;
use App\Domain\Payment\Event\PaymentRefundedEvent;
use App\Domain\Payment\ValueObject\PaymentStatus;
use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Payment
{
    private array $domainEvents = [];

    public function __construct(
        private readonly TransactionId $transactionId,
        private readonly Money $amount,
        private readonly BillingInformation $billingInformation,
        private PaymentStatus $status,
        private readonly ?string $gatewayToken = null,
        private readonly ?string $last4Digits = null,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {
        if ($amount->isZero()) {
            throw new InvalidArgumentException('Payment amount cannot be zero');
        }
    }

    public static function create(
        TransactionId $transactionId,
        Money $amount,
        BillingInformation $billingInformation,
        ?string $gatewayToken = null,
        ?string $last4Digits = null
    ): self {
        return new self(
            $transactionId,
            $amount,
            $billingInformation,
            PaymentStatus::pending(),
            $gatewayToken,
            $last4Digits
        );
    }

    public function approve(): void
    {
        if (!$this->status->isPending()) {
            throw new DomainException('Only pending payments can be approved');
        }

        $this->status = PaymentStatus::approved();
        $this->recordEvent(new PaymentApprovedEvent($this->transactionId, $this->amount));
    }

    public function decline(string $reason): void
    {
        if (!$this->status->isPending()) {
            throw new DomainException('Only pending payments can be declined');
        }

        $this->status = PaymentStatus::declined();
        $this->recordEvent(new PaymentDeclinedEvent($this->transactionId, $reason));
    }

    public function refund(Money $refundAmount, string $refundTransactionId): void
    {
        if (!$this->status->canBeRefunded()) {
            throw new DomainException('Payment cannot be refunded in current status: ' . $this->status->getValue());
        }

        if ($refundAmount->isGreaterThan($this->amount)) {
            throw new DomainException('Refund amount cannot exceed original payment amount');
        }

        if ($refundAmount->equals($this->amount)) {
            $this->status = PaymentStatus::refunded();
        } else {
            $this->status = PaymentStatus::partiallyRefunded();
        }

        $this->recordEvent(new PaymentRefundedEvent(
            $this->transactionId,
            TransactionId::fromString($refundTransactionId),
            $refundAmount
        ));
    }

    public function getTransactionId(): TransactionId
    {
        return $this->transactionId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getBillingInformation(): BillingInformation
    {
        return $this->billingInformation;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getGatewayToken(): ?string
    {
        return $this->gatewayToken;
    }

    public function getLast4Digits(): ?string
    {
        return $this->last4Digits;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    public function canBeRefunded(): bool
    {
        return $this->status->canBeRefunded();
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
