<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Entity\Customer;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Subscription\Entity\Subscription;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use App\Domain\Subscription\ValueObject\SubscriptionStatus;
use App\Infrastructure\Persistence\Entity\CustomerEntity;
use App\Infrastructure\Persistence\Entity\PlanEntity;
use App\Infrastructure\Persistence\Entity\SubscriptionEntity;
use Doctrine\ORM\EntityManagerInterface;

final class SubscriptionEntityMapper
{
    public function __construct(
        private readonly PlanEntityMapper $planMapper,
        private readonly CustomerEntityMapper $customerMapper,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function toDomain(SubscriptionEntity $entity): Subscription
    {
        $subscriptionId = SubscriptionId::fromString($entity->getSubscriptionId());
        $status = SubscriptionStatus::fromString($entity->getStatus());

        // Convert customer to domain and get billing information
        $customer = $this->customerMapper->toDomain($entity->getCustomer());
        $billingInformation = $customer->getBillingInformation();

        // Convert plan
        $plan = $this->planMapper->toDomain($entity->getPlan());

        // Create domain entity using constructor (since it's persisted data)
        return new Subscription(
            $subscriptionId,
            $plan,
            $billingInformation,
            $status,
            $entity->getStartDate(),
            $entity->getNextChargeDate(),
            $entity->getCustomerVaultId(),
            $entity->getCreatedAt()
        );
    }

    public function toEntity(Subscription $domain): SubscriptionEntity
    {
        $entity = new SubscriptionEntity();

        $entity->setSubscriptionId($domain->getSubscriptionId()->getValue());
        $entity->setStatus($domain->getStatus()->getValue());
        $entity->setStartDate($domain->getStartDate());
        $entity->setNextChargeDate($domain->getNextChargeDate());
        $entity->setCustomerVaultId($domain->getCustomerVaultId());
        $entity->setCreatedAt($domain->getCreatedAt());
        $entity->setUpdatedAt($domain->getCreatedAt());

        $customerEntity = $this->findOrCreateCustomer($domain->getBillingInformation(), $domain->getCustomerVaultId());
        $entity->setCustomer($customerEntity);

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

    private function findOrCreateCustomer(BillingInformation $billingInformation, ?string $customerVaultId): CustomerEntity
    {
        $customerRepository = $this->entityManager->getRepository(CustomerEntity::class);

        if ($customerVaultId) {
            $existingCustomer = $customerRepository->find($customerVaultId);

            if ($existingCustomer) {
                $existingCustomer->setEmail($billingInformation->getEmail()->getValue());
                $existingCustomer->setFirstName($billingInformation->getFirstName());
                $existingCustomer->setLastName($billingInformation->getLastName());

                $address = $billingInformation->getAddress();
                $existingCustomer->setStreet1($address->getStreet1());
                $existingCustomer->setStreet2($address->getStreet2());
                $existingCustomer->setCity($address->getCity());
                $existingCustomer->setState($address->getState());
                $existingCustomer->setPostalCode($address->getPostalCode());
                $existingCustomer->setCountry($address->getCountry());
                $existingCustomer->setPhone($billingInformation->getPhone());
                $existingCustomer->setUpdatedAt(new \DateTimeImmutable());

                return $existingCustomer;
            }
        }

        if (!$customerVaultId) {
            throw new \RuntimeException('Customer vault ID is required to create new customer');
        }

        $customer = Customer::create(
            $customerVaultId,
            $billingInformation->getEmail()->getValue(),
            $billingInformation->getFirstName(),
            $billingInformation->getLastName(),
            $billingInformation->getAddress()->getStreet1(),
            $billingInformation->getAddress()->getStreet2(),
            $billingInformation->getAddress()->getCity(),
            $billingInformation->getAddress()->getState(),
            $billingInformation->getAddress()->getPostalCode(),
            $billingInformation->getAddress()->getCountry(),
            $billingInformation->getPhone()
        );

        $customerEntity = $this->customerMapper->toEntity($customer);
        $this->entityManager->persist($customerEntity);

        return $customerEntity;
    }
}
