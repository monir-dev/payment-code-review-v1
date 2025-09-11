<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreatePlanCommand;
use App\Application\Response\CreatePlanCommandResponse;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Billing\Entity\Plan;
use App\Domain\Billing\Repository\PlanRepositoryInterface;
use App\Domain\Billing\ValueObject\PlanId;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Subscription\ValueObject\BillingCycle;
use App\Dto\CreatePlanDto;

final class CreatePlanCommandHandler
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly PaymentGatewayInterface $paymentGateway
    ) {
    }

    public function handle(CreatePlanCommand $command): CreatePlanCommandResponse
    {
        // Create BillingCycle from frequency - this encapsulates the domain logic
        $billingCycle = BillingCycle::fromFrequency($command->frequency);
        
        $planId = PlanId::generate()->getValue();
        $planDto = new CreatePlanDto(
            planId: $planId,
            planName: $command->planName,
            amount: $command->amount,
            frequency: $command->frequency,
            dayFrequency: $billingCycle->getDayFrequency() // Get from domain object
        );

        $nmiResult = $this->paymentGateway->createPlan($planDto);

        if ($nmiResult['status'] !== 'success') {
            throw new \Exception('NMI plan creation failed: ' . $nmiResult['message']);
        }

        $planIdObject = PlanId::fromString($planId);
        $money = Money::fromFloat($command->amount, $command->currencyCode);

        $plan = Plan::create($planIdObject, $command->planName, $money, $billingCycle);
        $this->planRepository->save($plan);

        return new CreatePlanCommandResponse(
            planId: $plan->getPlanId()->getValue(),
            planName: $plan->getName(),
            amount: $plan->getAmount()->format(),
            frequency: $plan->getBillingCycle()->getFrequency(),
            dayFrequency: $plan->getBillingCycle()->getDayFrequency(),
            status: $plan->getStatus()->getValue(),
            nmiResult: $nmiResult,
            events: $plan->getUncommittedEvents()
        );
    }
}
