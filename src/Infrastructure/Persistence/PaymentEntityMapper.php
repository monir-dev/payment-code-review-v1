<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\ValueObject\PaymentStatus;
use App\Domain\Payment\ValueObject\TransactionId;
use App\Domain\Shared\ValueObject\Address;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Shared\ValueObject\Money;
use App\Infrastructure\Persistence\Entity\PaymentEntity;

final class PaymentEntityMapper
{
    public function toDomain(PaymentEntity $entity): Payment
    {
        // Create value objects
        $transactionId = TransactionId::fromString($entity->getTransactionId());
        $money = Money::fromFloat($entity->getAmount(), $entity->getCurrencyCode());
        $status = PaymentStatus::fromString($entity->getPaymentStatus());
        
        $email = Email::fromString($entity->getBillingEmail());
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

        // Create domain entity using constructor (since it's persisted data)
        return new Payment(
            $transactionId,
            $money,
            $billingInformation,
            $status,
            $entity->getGatewayToken(),
            $entity->getLast4Digits(),
            $entity->getCreatedAt()
        );
    }

    public function toEntity(Payment $domain): PaymentEntity
    {
        $entity = new PaymentEntity();
        
        // Map basic properties
        $entity->setTransactionId($domain->getTransactionId()->getValue());
        $entity->setAmount($domain->getAmount()->getAmount());
        $entity->setCurrencyCode($domain->getAmount()->getCurrency()->getCode());
        $entity->setPaymentStatus($domain->getStatus()->getValue());
        
        // Map billing information
        $billing = $domain->getBillingInformation();
        $entity->setBillingFirstName($billing->getFirstName());
        $entity->setBillingLastName($billing->getLastName());
        $entity->setBillingEmail($billing->getEmail()->getValue());
        
        $address = $billing->getAddress();
        $entity->setBillingStreet1($address->getStreet1());
        $entity->setBillingStreet2($address->getStreet2());
        $entity->setBillingCity($address->getCity());
        $entity->setBillingState($address->getState());
        $entity->setBillingPostalCode($address->getPostalCode());
        $entity->setBillingCountry($address->getCountry());
        $entity->setBillingPhone($billing->getPhone());
        
        // Map optional fields
        $entity->setGatewayToken($domain->getGatewayToken());
        $entity->setLast4Digits($domain->getLast4Digits());
        $entity->setCreatedAt($domain->getCreatedAt());
        
        return $entity;
    }
}
