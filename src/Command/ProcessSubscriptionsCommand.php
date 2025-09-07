<?php

namespace App\Command;

use App\Repository\SubscriptionRepository;
use App\Service\NmiPaymentGateway;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-subscriptions',
    description: 'Process subscription rebilling for subscriptions that are due',
)]
class ProcessSubscriptionsCommand extends Command
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private NmiPaymentGateway $paymentGateway,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be processed without actually processing')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Process subscriptions even if they are not due yet')
            ->addOption('subscription-id', null, InputOption::VALUE_REQUIRED, 'Process only specific NMI subscription ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $specificSubscriptionId = $input->getOption('subscription-id');

        $io->title('Subscription Rebilling Processor');

        // Get subscriptions to process
        $subscriptions = [];
        
        if ($specificSubscriptionId) {
            $subscription = $this->subscriptionRepository->findOneBy(['subscription_id' => $specificSubscriptionId]);
            if ($subscription && $subscription->getStatus() === 'active') {
                $subscriptions[] = $subscription;
                $io->info("Processing specific subscription by NMI ID: {$specificSubscriptionId}");
            } else {
                $io->error("Subscription with NMI ID '{$specificSubscriptionId}' not found or not active.");
                return Command::FAILURE;
            }
        } else {
            $targetDate = $force ? new \DateTime('+1 year') : new \DateTime();
            $subscriptions = $this->subscriptionRepository->findDueForCharging($targetDate);
            $io->info("Processing all subscriptions due for charging" . ($force ? " (forced)" : ""));
        }

        if (empty($subscriptions)) {
            $io->success('No subscriptions due for processing.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Found %d subscription(s) to process', count($subscriptions)));

        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $processed++;
            
            $io->write(sprintf(
                '[%d/%d] Processing subscription %s (%s) - $%s %s... ',
                $processed,
                count($subscriptions),
                $subscription->getSubscriptionId(),
                $subscription->getCustomerEmail(),
                number_format($subscription->getAmount(), 2),
                $subscription->getFrequency()
            ));

            if ($isDryRun) {
                $io->writeln('<info>SKIPPED (dry-run)</info>');
                continue;
            }

            try {
                $result = $this->paymentGateway->processRebilling(
                    $subscription->getSubscriptionId(),
                    $subscription->getCustomerVaultId(),
                    $subscription->getAmount()
                );

                if ($result['status'] === 'success') {
                    // Update subscription next charge date using repository
                    $nextChargeDate = $this->calculateNextChargeDate($subscription);
                    $this->subscriptionRepository->updateAfterRebilling($subscription, $nextChargeDate, true);
                    
                    $successful++;
                    $io->writeln('<info>SUCCESS</info> (Transaction: ' . $result['transaction_id'] . ')');
                    
                    $this->logger->info('Automatic rebilling processed successfully', [
                        'subscription_id' => $subscription->getSubscriptionId(),
                        'local_id' => $subscription->getId(),
                        'transaction_id' => $result['transaction_id'],
                        'amount' => $subscription->getAmount(),
                        'customer_email' => $subscription->getCustomerEmail()
                    ]);
                } else {
                    $failed++;
                    $io->writeln('<error>FAILED</error> (' . $result['message'] . ')');
                    
                    $this->logger->warning('Automatic rebilling failed', [
                        'subscription_id' => $subscription->getSubscriptionId(),
                        'local_id' => $subscription->getId(),
                        'customer_email' => $subscription->getCustomerEmail(),
                        'message' => $result['message']
                    ]);
                }
            } catch (\Exception $e) {
                $failed++;
                $io->writeln('<error>ERROR</error> (' . $e->getMessage() . ')');
                
                $this->logger->error('Automatic rebilling exception', [
                    'subscription_id' => $subscription->getSubscriptionId(),
                    'local_id' => $subscription->getId(),
                    'customer_email' => $subscription->getCustomerEmail(),
                    'exception' => $e->getMessage()
                ]);
            }
        }

        // Summary
        $io->newLine();
        $io->section('Processing Summary');
        
        if ($isDryRun) {
            $io->info(sprintf('DRY RUN: Would have processed %d subscriptions', $processed));
        } else {
            $io->table(
                ['Metric', 'Count'],
                [
                    ['Total Processed', $processed],
                    ['Successful', $successful],
                    ['Failed', $failed],
                ]
            );
        }

        if ($failed > 0) {
            $io->warning(sprintf('%d subscription(s) failed to process. Check logs for details.', $failed));
        }

        if ($successful > 0) {
            $io->success(sprintf('%d subscription(s) processed successfully!', $successful));
        }

        return ($failed > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    private function calculateNextChargeDate($subscription): \DateTime
    {
        $nextChargeDate = clone $subscription->getNextChargeDate();
        
        match($subscription->getFrequency()) {
            'weekly' => $nextChargeDate->add(new \DateInterval('P7D')),
            'monthly' => $nextChargeDate->add(new \DateInterval('P1M')),
            'yearly' => $nextChargeDate->add(new \DateInterval('P1Y')),
        };
        
        return $nextChargeDate;
    }
}
