<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Command\ProcessPaymentCommand;
use App\Application\Command\CreateSubscriptionCommand;
use App\Application\Command\InitializePaymentCommand;
use App\Application\Command\CompletePaymentCommand;
use App\Application\Handler\ProcessPaymentCommandHandler;
use App\Application\Handler\CreateSubscriptionCommandHandler;
use App\Application\Handler\InitializePaymentCommandHandler;
use App\Application\Handler\CompletePaymentCommandHandler;
use App\Application\Service\PaymentSessionService;
use App\Presentation\Form\CheckoutType;
use App\Presentation\Form\RefundType;
use App\Presentation\Form\Step2Type;
use App\Repository\PaymentTransactionRepository;
use App\Infrastructure\Repository\PlanRepository;
use App\Service\NmiPaymentGateway;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Domain\Subscription\ValueObject\SubscriptionId;
use App\Application\Handler\CancelSubscriptionCommandHandler;
use App\Application\Command\CancelSubscriptionCommand;
use App\Domain\Shared\ValueObject\BillingInformation;
use App\Domain\Shared\ValueObject\Email;
use App\Domain\Shared\ValueObject\Address;

class PaymentController extends AbstractController
{
    public function __construct(
        private readonly CreateSubscriptionCommandHandler $createSubscriptionHandler,
        private readonly CancelSubscriptionCommandHandler $cancelSubscriptionHandler,
        private readonly InitializePaymentCommandHandler $initializePaymentHandler,
        private readonly CompletePaymentCommandHandler $completePaymentHandler,
        private readonly PaymentSessionService $paymentSessionService,
        private readonly NmiPaymentGateway $paymentGateway,
        private readonly LoggerInterface $logger,
        private readonly PlanRepository $planRepository,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
    ) {
    }

    #[Route('/checkout', name: 'app_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request): Response
    {
        // Handle token-id from Step 3 (Payment Callback) - DDD Approach
        if ($tokenId = $request->query->get('token-id')) {
            // Get session data before processing payment
            $sessionData = $this->paymentSessionService->getPaymentSessionData();
            
            $completePaymentCommand = new CompletePaymentCommand(
                tokenId: $tokenId,
                paymentAmount: $sessionData['payment_amount'],
                subscriptionData: $sessionData['subscription_data']
            );
            
            $result = $this->completePaymentHandler->handle($completePaymentCommand);

            if ($result['status'] === 'success') {
                $this->addFlash('success', 'Payment successful! Transaction ID: ' . $result['transaction_id']);
                // Clear session data after successful processing
                $this->paymentSessionService->clearPaymentSessionData();
                // Note: Subscription creation is now handled by PaymentCompletedEventListener
                // automatically via domain events - no manual subscription creation needed here
            } elseif ($result['status'] === 'declined') {
                $this->addFlash('danger', 'Payment declined: ' . ($result['decline_message'] ?? 'Payment declined'));
            } else {
                $this->addFlash('danger', 'Payment failed: ' . ($result['error_message'] ?? 'Payment failed'));
            }

            return $this->redirectToRoute('app_checkout');
        }

        // Get active plans for the form
        $activePlans = $this->planRepository->findActivePlans();

        $form = $this->createForm(CheckoutType::class, null, [
            'active_plans' => $activePlans
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Determine the amount based on subscription vs one-time payment
            $amount = $data['amount'];
            
            if ($data['subscribeAndCheckout'] && isset($data['plan_id']) && $data['plan_id'] !== null) {
                // Get amount from selected plan by finding the plan with matching ID
                $selectedPlan = null;
                foreach ($activePlans as $plan) {
                    if ($plan->getPlanId()->getValue() === $data['plan_id']) {
                        $selectedPlan = $plan;
                        break;
                    }
                }
                
                if ($selectedPlan) {
                    $amount = $selectedPlan->getAmount()->getAmount();
                } else {
                    throw new \Exception('Selected plan not found: ' . $data['plan_id']);
                }
            }

            if ($amount === null || $amount <= 0) {
                $this->addFlash('danger', 'Invalid amount specified');
                return $this->render('payment/checkout.html.twig', [
                    'checkoutForm' => $form->createView(),
                ]);
            }

            // Step 1: Initialize payment - DDD Approach
            $redirectUrl = $this->generateUrl('app_checkout', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

            // Prepare subscription data for command if subscription is selected
            $selectedPlan = null;
            $selectedPlanJson = null;
            if ($data['subscribeAndCheckout'] && isset($data['plan_id']) && $data['plan_id'] !== null) {
                // Get the selected plan by finding the plan with matching ID
                foreach ($activePlans as $plan) {
                    if ($plan->getPlanId()->getValue() === $data['plan_id']) {
                        $selectedPlan = $plan;
                        break;
                    }
                }

                if (!$selectedPlan) {
                    throw new \Exception('Selected plan not found: ' . $data['plan_id']);
                }

                // Serialize plan data for command
                $selectedPlanJson = json_encode([
                    'plan_id' => $selectedPlan->getPlanId()->getValue(),
                    'name' => $selectedPlan->getName(),
                    'amount' => $selectedPlan->getAmount()->getAmount(),
                    'frequency' => $selectedPlan->getBillingCycle()->getFrequency()
                ]);
            }

            // Create payment initialization command
            $initializePaymentCommand = new InitializePaymentCommand(
                amount: $amount,
                currency: $data['currency'],
                redirectUrl: $redirectUrl,
                billingFirstName: $data['billingFirstName'],
                billingLastName: $data['billingLastName'],
                billingEmail: $data['billingEmail'] ?? '',
                billingAddress1: $data['billingAddress1'] ?? '',
                billingAddress2: $data['billingAddress2'] ?? '',
                billingCity: $data['billingCity'] ?? '',
                billingState: $data['billingState'] ?? '',
                billingPostal: $data['billingPostal'],
                billingCountry: $data['billingCountry'],
                billingPhone: $data['billingPhone'] ?? null,
                isSubscription: $data['subscribeAndCheckout'] && $selectedPlan !== null,
                planId: $selectedPlan?->getPlanId()->getValue(),
                selectedPlanData: $selectedPlanJson
            );

            // Handle payment initialization through command handler
            $result = $this->initializePaymentHandler->handle($initializePaymentCommand);

            if ($result['status'] === 'success') {
                // Store payment amount and subscription data using PaymentSessionService
                $subscriptionData = null;
                if ($selectedPlan) {
                    $subscriptionData = [
                        'selected_plan' => $selectedPlan, // Keep the plan object for backward compatibility
                        'customer_email' => $data['billingEmail'],
                        'billing_first_name' => $data['billingFirstName'],
                        'billing_last_name' => $data['billingLastName'],
                        'billing_address1' => $data['billingAddress1'] ?? '',
                        'billing_address2' => $data['billingAddress2'] ?? '',
                        'billing_city' => $data['billingCity'] ?? '',
                        'billing_state' => $data['billingState'] ?? '',
                        'billing_postal' => $data['billingPostal'],
                        'billing_country' => $data['billingCountry'],
                        'billing_phone' => $data['billingPhone'] ?? '',
                    ];
                }
                
                $this->paymentSessionService->storePaymentSessionData($amount, $subscriptionData);

                // Create Step 2 form with the NMI form URL as action
                $step2Form = $this->createForm(Step2Type::class, null, [
                    'action' => $result['form_url']
                ]);

                // Render Step 2 form
                return $this->render('payment/step2.html.twig', [
                    'step2Form' => $step2Form->createView(),
                    'amount' => $amount
                ]);
            } else {
                $this->addFlash('danger', 'Failed to initialize payment: ' . ($result['message'] ?? 'Unknown error'));
            }
        }

        return $this->render('payment/checkout.html.twig', [
            'checkoutForm' => $form->createView(),
        ]);
    }

    #[Route('/refund', name: 'app_refund', methods: ['GET', 'POST'])]
    public function refund(Request $request): Response
    {
        $form = $this->createForm(RefundType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $result = $this->paymentGateway->processRefund(
                $data['transactionId'],
                $data['refundAmount']
            );

            if ($result['status'] === 'success') {
                // Cancel related subscriptions using DDD approach
                $cancelledSubscriptions = $this->cancelSubscriptionsByTransactionId($data['transactionId']);

                $this->addFlash('success', 'Refund successful! New Transaction ID: ' . $result['transaction_id'] . $cancelledSubscriptions);
                $this->logger->info('Refund successful', ['transaction_id' => $result['transaction_id']]);
                return $this->redirectToRoute('app_refund');
            } else {
                $this->addFlash('danger', 'Refund failed: ' . $result['message']);
                $this->logger->error('Refund failed', ['message' => $result['message']]);
                return $this->redirectToRoute('app_refund');
            }
        }

        return $this->render('payment/refund.html.twig', [
            'refundForm' => $form->createView(),
        ]);
    }

    #[Route('/api/transactions', name: 'app_payment_history', methods: ['GET'])]
    public function getTransactions(Request $request, PaymentTransactionRepository $repository): Response
    {
        $transactions = $repository->by($request);
        $transactionData = [];

        foreach ($transactions as $transaction) {
            $transactionData[] = [
                'uuid' => $transaction->getUuid(),
                'transaction_id' => $transaction->getTransactionId(),
                'amount' => $transaction->getAmount(),
                'currency_code' => $transaction->getCurrencyCode(),
                'payment_status' => $transaction->getPaymentStatus(),
                'last4_digits' => $transaction->getLast4Digits(),
                'created_at' => $transaction->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new Response(
            json_encode($transactionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json'],
        );
    }


    private function cancelSubscriptionsByTransactionId(string $transactionId): string
    {
        // Find subscriptions by transaction ID using DDD repository
        $subscriptions = $this->subscriptionRepository->findByOriginalTransactionId(
            \App\Domain\Payment\ValueObject\TransactionId::fromString($transactionId)
        );

        if (empty($subscriptions)) {
            return '';
        }

        $cancelledCount = 0;
        $cancelMessage = '';

        foreach ($subscriptions as $subscription) {
            try {
                // Use DDD command handler to cancel subscription
                $cancelCommand = new CancelSubscriptionCommand(
                    subscriptionId: $subscription->getSubscriptionId()->getValue(),
                    reason: 'Payment refund - automatic cancellation',
                    cancelWithGateway: true,
                    cancelledBy: 'system:refund-process'
                );

                $result = $this->cancelSubscriptionHandler->handle($cancelCommand);

                if ($result['gateway_cancelled']) {
                    $cancelledCount++;




                    $this->logger->info('Subscription cancelled due to refund via DDD', [
                        'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                        'original_transaction_id' => $transactionId,
                        'customer_email' => $subscription->getBillingInformation()->getEmail()->getValue(),
                        'refund_triggered' => true,
                        'method' => 'ddd-command-handler'
                    ]);
                } else {
                    $this->logger->warning('Failed to cancel subscription during refund via DDD', [
                        'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                        'original_transaction_id' => $transactionId,
                        'gateway_result' => $result['gateway_result'],
                        'refund_triggered' => true,
                        'method' => 'ddd-command-handler'
                    ]);
                }

            } catch (\Exception $e) {
                $this->logger->error('Exception while cancelling subscription during refund via DDD', [
                    'subscription_id' => $subscription->getSubscriptionId()->getValue(),
                    'original_transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                    'refund_triggered' => true,
                    'method' => 'ddd-command-handler'
                ]);
            }
        }

        if ($cancelledCount > 0) {
            $cancelMessage = sprintf(' %d subscription(s) have been automatically cancelled via DDD.', $cancelledCount);
        } elseif (count($subscriptions) > 0) {
            $cancelMessage = ' Warning: Some subscriptions could not be cancelled automatically. Please check manually.';
        }

        return $cancelMessage;
    }
}
