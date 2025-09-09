<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\TogglePlanStatusCommand;
use App\Domain\Billing\Repository\PlanRepositoryInterface;
use App\Domain\Billing\ValueObject\PlanId;
use Psr\Log\LoggerInterface;

final class TogglePlanStatusCommandHandler
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(TogglePlanStatusCommand $command): array
    {
        try {
            // Find the plan
            $planId = PlanId::fromString($command->planId);
            $plan = $this->planRepository->findByPlanId($planId);

            if (!$plan) {
                throw new \DomainException('Plan not found with ID: ' . $command->planId);
            }

            // Store the original status for logging
            $originalStatus = $plan->getStatus()->getValue();

            // Use domain logic for status toggle
            if ($plan->isActive()) {
                $plan->deactivate();
                $action = 'deactivated';
            } else {
                $plan->activate();
                $action = 'activated';
            }

            // Save the plan (this will trigger domain event publishing)
            $this->planRepository->save($plan);

            $newStatus = $plan->getStatus()->getValue();

            // Log the successful operation
            $this->logger->info('Plan status toggled via DDD command handler', [
                'plan_id' => $plan->getPlanId()->getValue(),
                'plan_name' => $plan->getName(),
                'original_status' => $originalStatus,
                'new_status' => $newStatus,
                'action' => $action
            ]);

            return [
                'success' => true,
                'plan_id' => $plan->getPlanId()->getValue(),
                'plan_name' => $plan->getName(),
                'original_status' => $originalStatus,
                'new_status' => $newStatus,
                'action' => $action,
                'message' => "Plan {$action} successfully",
                'events' => $plan->getUncommittedEvents()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Plan status toggle failed', [
                'plan_id' => $command->planId,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);

            throw $e; // Re-throw to be handled by controller
        }
    }
}
