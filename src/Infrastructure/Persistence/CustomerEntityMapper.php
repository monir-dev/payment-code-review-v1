<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Entity\Customer;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\Email;
use App\Infrastructure\Persistence\Entity\CustomerEntity;

final class CustomerEntityMapper
{
    public function toDomain(CustomerEntity $entity): Customer
    {
        $email = Email::fromString($entity->getEmail());
        $address = Address::create(
            $entity->getStreet1(),
            $entity->getStreet2(),
            $entity->getCity(),
            $entity->getState(),
            $entity->getPostalCode(),
            $entity->getCountry()
        );

        return new Customer(
            $entity->getCustomerVaultId(),
            $email,
            $entity->getFirstName(),
            $entity->getLastName(),
            $address,
            $entity->getPhone(),
            $entity->getCreatedAt()
        );
    }

    public function toEntity(Customer $domain): CustomerEntity
    {
        $entity = new CustomerEntity();

        // Map basic properties
        $entity->setCustomerVaultId($domain->getCustomerVaultId());
        $entity->setEmail($domain->getEmail()->getValue());
        $entity->setFirstName($domain->getFirstName());
        $entity->setLastName($domain->getLastName());
        $entity->setPhone($domain->getPhone());
        $entity->setCreatedAt($domain->getCreatedAt());
        $entity->setUpdatedAt(new \DateTimeImmutable()); // Set current time for updatedAt

        // Map address
        $address = $domain->getAddress();
        $entity->setStreet1($address->getStreet1());
        $entity->setStreet2($address->getStreet2());
        $entity->setCity($address->getCity());
        $entity->setState($address->getState());
        $entity->setPostalCode($address->getPostalCode());
        $entity->setCountry($address->getCountry());

        return $entity;
    }
}
