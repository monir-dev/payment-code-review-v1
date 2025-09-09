<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Subscription\Entity\Subscription;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use App\Domain\Subscription\ValueObject\SubscriptionStatus;
use App\Infrastructure\Persistence\Entity\PlanEntity;
use App\Infrastructure\Persistence\Entity\SubscriptionEntity;
use Doctrine\ORM\EntityManagerInterface;

final class SubscriptionEntityMapper
{
    public function __construct(
        private readonly PlanEntityMapper $planMapper,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function toDomain(SubscriptionEntity $entity): Subscription
    {
        $subscriptionId = SubscriptionId::fromString($entity->getSubscriptionId());
        $status = SubscriptionStatus::fromString($entity->getStatus());

        $email = Email::fromString($entity->getCustomerEmail());
        $address = Address::create(
            $entity->getBillingStreet1(),
            $entity->getBillingStreet2(),
            $entity->getBillingCity(),
            $entity->getBillingState(),
            $entity->getBillingPostalCode(),
            $entity->getBillingCountry()
        );

        $billingInformation = new BillingInformation(
            $entity->getBillingFirstName(),
            $entity->getBillingLastName(),
            $email,
            $address,
            $entity->getBillingPhone()
        );

        // Convert plan
        $plan = $this->planMapper->toDomain($entity->getPlan());

        $originalTransactionId = $entity->getOriginalTransactionId()
            ? TransactionId::fromString($entity->getOriginalTransactionId())
            : null;

        // Create domain entity using constructor (since it's persisted data)
        return new Subscription(
            $subscriptionId,
            $plan,
            $billingInformation,
            $status,
            $entity->getStartDate(),
            $entity->getNextChargeDate(),
            $entity->getCustomerVaultId(),
            $originalTransactionId,
            $entity->getCreatedAt()
        );
    }

    public function toEntity(Subscription $domain): SubscriptionEntity
    {
        $entity = new SubscriptionEntity();

        // Map basic properties
        $entity->setSubscriptionId($domain->getSubscriptionId()->getValue());
        $entity->setStatus($domain->getStatus()->getValue());
        $entity->setStartDate($domain->getStartDate());
        $entity->setNextChargeDate($domain->getNextChargeDate());
        $entity->setCustomerVaultId($domain->getCustomerVaultId());
        $entity->setOriginalTransactionId(
            $domain->getOriginalTransactionId()?->getValue()
        );
        $entity->setCreatedAt($domain->getCreatedAt());
        $entity->setUpdatedAt($domain->getCreatedAt()); // Set updatedAt to same as createdAt for new records

        // Map amount, frequency, and currency from plan
        $entity->setAmount($domain->getPlan()->getAmount()->getAmount());
        $entity->setFrequency($domain->getPlan()->getBillingCycle()->getFrequency());
        $entity->setCurrencyCode($domain->getAmount()->getCurrency()->getCode());

        // Map billing information
        $billing = $domain->getBillingInformation();
        $entity->setCustomerEmail($billing->getEmail()->getValue());
        $entity->setBillingFirstName($billing->getFirstName());
        $entity->setBillingLastName($billing->getLastName());

        $address = $billing->getAddress();
        $entity->setBillingStreet1($address->getStreet1());
        $entity->setBillingStreet2($address->getStreet2());
        $entity->setBillingCity($address->getCity());
        $entity->setBillingState($address->getState());
        $entity->setBillingPostalCode($address->getPostalCode());
        $entity->setBillingCountry($address->getCountry());
        $entity->setBillingPhone($billing->getPhone());

        $planRepository = $this->entityManager->getRepository(PlanEntity::class);
        $planEntity = $planRepository->findOneBy(['planId' => $domain->getPlan()->getPlanId()->getValue()]);

        if (!$planEntity) {
            throw new \RuntimeException(
                'PlanEntity not found for plan ID: ' . $domain->getPlan()->getPlanId()->getValue()
            );
        }

        $entity->setPlan($planEntity);

        return $entity;
    }
}
