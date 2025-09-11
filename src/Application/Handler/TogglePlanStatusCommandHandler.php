<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\TogglePlanStatusCommand;
use App\Application\Response\TogglePlanStatusCommandResponse;
use App\Domain\Billing\Repository\PlanRepositoryInterface;
use App\Domain\Billing\ValueObject\PlanId;
use Exception;
use Psr\Log\LoggerInterface;

final class TogglePlanStatusCommandHandler
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(TogglePlanStatusCommand $command): TogglePlanStatusCommandResponse
    {
        try {
            // Find the plan
            $planId = PlanId::fromString($command->planId);
            $plan = $this->planRepository->findByPlanId($planId);

            if (!$plan) {
                throw new \DomainException('Plan not found with ID: ' . $command->planId);
            }

            if ($plan->isActive()) {
                $plan->deactivate();
                $action = 'deactivated';
            } else {
                $plan->activate();
                $action = 'activated';
            }

            // Save the plan (this will trigger domain event publishing)
            $this->planRepository->save($plan);

            return new TogglePlanStatusCommandResponse(
                success: true,
                planId: $plan->getPlanId()->getValue(),
                planName: $plan->getName(),
                originalStatus: $plan->getStatus()->getValue(),
                newStatus: $plan->getStatus()->getValue(),
                action: $action,
                message: "Plan {$action} successfully",
                events: $plan->getUncommittedEvents()
            );

        } catch (Exception $e) {
            $this->logger->error('Plan status toggle failed', [
                'plan_id' => $command->planId,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);

            throw $e; // Re-throw to be handled by controller
        }
    }
}
