<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Command\RebillSubscriptionCommand;
use App\Application\Handler\RebillSubscriptionCommandHandler;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-automatic-rebilling',
    description: 'Process automatic rebilling',
)]
class ProcessAutomaticRebillingCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly RebillSubscriptionCommandHandler $rebillHandler,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be processed without actually processing')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Process subscriptions even if they are not due yet')
            ->addOption('subscription-id', null, InputOption::VALUE_REQUIRED, 'Process only specific subscription ID')
            ->addOption('gateway', null, InputOption::VALUE_NONE, 'Process with payment gateway (default: false for safety)')
            ->setHelp('
This command processes automatic rebilling for subscriptions using DDD architecture.

Examples:
  <info>%command.full_name% --dry-run</info>                    Show what would be rebilled
  <info>%command.full_name% --force</info>                       Force rebill all active subscriptions
  <info>%command.full_name% --gateway</info>                     Process with actual payment gateway
  <info>%command.full_name% --subscription-id=sub_123</info>     Rebill specific subscription
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $specificSubscriptionId = $input->getOption('subscription-id');
        $processWithGateway = $input->getOption('gateway');

        $io->title('Automatic Rebilling Processor');

        // Safety warning for gateway processing
        if ($processWithGateway && !$isDryRun) {
            $io->warning([
                'GATEWAY PROCESSING ENABLED',
                'This will charge actual payments through the NMI gateway.',
                'Make sure this is intended for production use.'
            ]);

            if (!$io->confirm('Do you want to continue with gateway processing?', false)) {
                $io->note('Operation cancelled by user.');
                return Command::SUCCESS;
            }
        }

        // Get subscriptions to process
        $subscriptions = [];

        if ($specificSubscriptionId) {
            $subscription = $this->subscriptionRepository->findBySubscriptionId(
                SubscriptionId::fromString($specificSubscriptionId)
            );

            if ($subscription && $subscription->getStatus()->isActive()) {
                $subscriptions[] = $subscription;
                $io->info("Processing specific subscription: {$specificSubscriptionId}");
            } else {
                $io->error("Subscription '{$specificSubscriptionId}' not found or not active.");
                return Command::FAILURE;
            }
        } else {
            $targetDate = $force ? new DateTimeImmutable('+1 year') : new DateTimeImmutable();
            $subscriptions = $this->subscriptionRepository->findDueForCharging($targetDate);
            $io->info("Processing subscriptions due for charging" . ($force ? " (forced - all active)" : ""));
        }

        if (empty($subscriptions)) {
            $io->success('No subscriptions due for processing.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Found %d subscription(s) to process', count($subscriptions)));

        $io->table(
            ['Subscription ID', 'Customer', 'Amount', 'Frequency', 'Next Charge', 'Status'],
            array_map(fn($sub) => [
                $sub->getSubscriptionId()->getValue(),
                $sub->getBillingInformation()->getEmail()->getValue(),
                $sub->getAmount()->format(),
                $sub->getBillingCycle()->getFrequency(),
                $sub->getNextChargeDate()->format('Y-m-d'),
                $sub->getStatus()->getValue()
            ], $subscriptions)
        );

        if ($isDryRun) {
            $io->info('DRY RUN - No actual processing will occur');
            $io->note('Use --gateway flag to process with payment gateway when running for real.');
            return Command::SUCCESS;
        }

        // Process rebilling
        $processed = 0;
        $successful = 0;
        $failed = 0;

        $progressBar = $io->createProgressBar(count($subscriptions));
        $progressBar->setFormat('verbose');
        $progressBar->start();

        foreach ($subscriptions as $subscription) {
            $processed++;
            $progressBar->advance();

            try {
                $rebillCommand = new RebillSubscriptionCommand(
                    subscriptionId: $subscription->getSubscriptionId()->getValue(),
                    customAmount: null, // Use default plan amount
                    processWithGateway: $processWithGateway,
                    reason: 'Automatic rebilling',
                    initiatedBy: 'system:automatic-rebill-command'
                );

                $result = $this->rebillHandler->handle($rebillCommand);

                if ($result['gateway_processed'] || !$processWithGateway) {
                    $successful++;

                    $this->logger->info('Automatic rebilling processed successfully', [
                        'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                        'transaction_id' => $result['transaction_id'],
                        'amount' => $result['amount'],
                        'customer_email' => $subscription->getBillingInformation()->getEmail()->getValue(),
                        'gateway_processed' => $result['gateway_processed'],
                        'method' => 'ddd-command-handler'
                    ]);
                } else {
                    $failed++;

                    $this->logger->warning('Automatic rebilling failed', [
                        'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                        'customer_email' => $subscription->getBillingInformation()->getEmail()->getValue(),
                        'gateway_result' => $result['gateway_result'],
                        'method' => 'ddd-command-handler'
                    ]);
                }

            } catch (\Exception $e) {
                $failed++;

                $this->logger->error('Automatic rebilling exception', [
                    'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                    'customer_email' => $subscription->getBillingInformation()->getEmail()->getValue(),
                    'exception' => $e->getMessage(),
                    'method' => 'ddd-command-handler'
                ]);
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        // Summary
        $io->section('📊 Processing Summary');

        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $processed],
                ['Successful', $successful],
                ['Failed', $failed],
                ['Gateway Processing', $processWithGateway ? 'Enabled' : 'Disabled'],
            ]
        );

        if ($failed > 0) {
            $io->warning(sprintf('%d subscription(s) failed to process. Check logs for details.', $failed));
        }

        if ($successful > 0) {
            $io->success(sprintf('%d subscription(s) processed successfully!', $successful));
        }

        // Return appropriate exit code
        return ($failed > 0) ? Command::FAILURE : Command::SUCCESS;
    }
}
