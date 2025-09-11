<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Handler\CreatePlanCommandHandler;
use App\Application\Command\CreatePlanCommand;
use App\Application\Service\PaymentGatewayInterface;
use App\Domain\Billing\Repository\PlanRepositoryInterface;
use App\Domain\Shared\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CreatePlanCommandHandlerTest extends TestCase
{
    public function testSuccessfulPlanCreation(): void
    {
        $mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $mockRepository = $this->createMock(PlanRepositoryInterface::class);
        
        $mockGateway
            ->method('createPlan')
            ->willReturn(new \App\Application\Response\Gateway\CreatePlanResponse(
                status: 'success',
                planId: 'nmi-plan-123',
                message: 'Plan created successfully'
            ));
        
        $mockRepository
            ->expects($this->once())
            ->method('save');

        $handler = new CreatePlanCommandHandler($mockRepository, $mockGateway);
        
        $command = new CreatePlanCommand(
            planName: 'Premium Plan',
            amount: Money::fromFloat(29.99, 'USD'),
            currencyCode: 'USD',
            frequency: 'monthly'
        );

        $result = $handler->handle($command);
        
        $this->assertEquals('Premium Plan', $result->planName);
        $this->assertEquals('success', $result->nmiResult['status']);
    }
}