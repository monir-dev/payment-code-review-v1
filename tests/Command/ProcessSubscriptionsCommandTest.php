<?php

namespace App\Tests\Command;

use App\Command\ProcessSubscriptionsCommand;
use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Service\NmiPaymentGateway;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ProcessSubscriptionsCommandTest extends TestCase
{
    private MockObject & SubscriptionRepository $subscriptionRepository;
    private MockObject & NmiPaymentGateway $paymentGateway;
    private MockObject & LoggerInterface $logger;
    private ProcessSubscriptionsCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->paymentGateway = $this->createMock(NmiPaymentGateway::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->command = new ProcessSubscriptionsCommand(
            $this->subscriptionRepository,
            $this->paymentGateway,
            $this->logger
        );

        $application = new Application();
        $application->add($this->command);
        
        $this->commandTester = new CommandTester($this->command);
    }


    public function testExecuteWithSuccessfulRebilling(): void
    {
        $subscription = $this->createMockSubscription();
        
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findDueForCharging')
            ->willReturn([$subscription]);

        $this->paymentGateway
            ->expects($this->once())
            ->method('processRebilling')
            ->willReturn([
                'status' => 'success',
                'transaction_id' => 'TXN_SUCCESS_123'
            ]);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('updateAfterRebilling');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Automatic rebilling processed successfully');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('SUCCESS', $output);
        $this->assertStringContainsString('TXN_SUCCESS_123', $output);
        $this->assertStringContainsString('1 subscription(s) processed successfully', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithFailedRebilling(): void
    {
        $subscription = $this->createMockSubscription();
        
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findDueForCharging')
            ->willReturn([$subscription]);

        $this->paymentGateway
            ->expects($this->once())
            ->method('processRebilling')
            ->willReturn([
                'status' => 'error',
                'message' => 'Payment declined'
            ]);

        // Repository should not be updated on failure
        $this->subscriptionRepository
            ->expects($this->never())
            ->method('updateAfterRebilling');

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Automatic rebilling failed');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('FAILED', $output);
        $this->assertStringContainsString('Payment declined', $output);
        $this->assertStringContainsString('1 subscription(s) failed to process', $output);
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
    }


    private function createMockSubscription(string $subscriptionId = 'SUB_123', string $email = 'test@example.com'): MockObject & Subscription
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getSubscriptionId')->willReturn($subscriptionId);
        $subscription->method('getCustomerVaultId')->willReturn('VAULT_456');
        $subscription->method('getAmount')->willReturn(29.99);
        $subscription->method('getFrequency')->willReturn('monthly');
        $subscription->method('getStatus')->willReturn('active');
        $subscription->method('getCustomerEmail')->willReturn($email);
        $subscription->method('getId')->willReturn(42);
        $subscription->method('getNextChargeDate')->willReturn(new \DateTime('2024-01-01'));
        
        return $subscription;
    }
}
