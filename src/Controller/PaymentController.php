<?php

namespace App\Controller;

use App\Dto\CreateSubscriptionDto;
use App\Form\CheckoutType;
use App\Form\RefundType;
use App\Form\Step2Type;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use App\Service\NmiPaymentGateway;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    private NmiPaymentGateway $paymentGateway;
    private LoggerInterface $logger;
    private PlanRepository $planRepository;
    private SubscriptionRepository $subscriptionRepository;

    public function __construct(
        NmiPaymentGateway $paymentGateway, 
        LoggerInterface $logger, 
        PlanRepository $planRepository,
        SubscriptionRepository $subscriptionRepository
    ) {
        $this->paymentGateway = $paymentGateway;
        $this->logger = $logger;
        $this->planRepository = $planRepository;
        $this->subscriptionRepository = $subscriptionRepository;
    }

    #[Route('/checkout', name: 'app_checkout')]
    public function checkout(Request $request)
    {
        // Handle token-id from Step 3
        if ($request->query->get('token-id')) {
            $result = $this->paymentGateway->completeTransaction($request);

            if ($result['status'] === 'success') {
                $this->addFlash('success', 'Payment successful! Transaction ID: ' . $result['transaction_id']);
                $this->logger->info('Checkout successful', ['transaction_id' => $result['transaction_id']]);

                // Create subscription if subscription data exists in session
                $subscriptionData = $request->getSession()->get('subscription_data');
                if ($subscriptionData) {
                    try {
                        $this->createSubscriptionFromPayment($subscriptionData, $request, $result['transaction_id']);
                    } catch (\Exception $e) {
                        $this->logger->error('Subscription creation failed after successful payment', [
                            'transaction_id' => $result['transaction_id'],
                            'error' => $e->getMessage(),
                            'subscription_data' => $subscriptionData
                        ]);
                        $this->addFlash('warning', 'Payment successful, but subscription setup failed. Please contact support.');
                    }
                    // Clear subscription data from session
                    $request->getSession()->remove('subscription_data');
                }
            } elseif ($result['status'] === 'declined') {
                $this->addFlash('danger', 'Payment declined: ' . $result['decline_message']);
                $this->logger->warning('Payment declined', ['decline_message' => $result['decline_message']]);
            } else {
                $this->addFlash('danger', 'Payment failed: ' . $result['error_message']);
                $this->logger->error('Checkout failed', ['error_message' => $result['error_message']]);
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

            // Step 1: Initialize payment
            $redirectUrl = $this->generateUrl('app_checkout', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

            $billingInfo = [
                'first-name' => $data['billingFirstName'],
                'last-name' => $data['billingLastName'],
                'address1' => $data['billingAddress1'] ?? '',
                'address2' => $data['billingAddress2'] ?? '',
                'city' => $data['billingCity'] ?? '',
                'state' => $data['billingState'] ?? '',
                'postal' => $data['billingPostal'],
                'country' => $data['billingCountry'],
                'email' => $data['billingEmail'] ?? '',
                'phone' => $data['billingPhone'] ?? ''
            ];

            $result = $this->paymentGateway->initializePayment(
                $data['amount'],
                $data['currency'],
                $redirectUrl,
                $billingInfo
            );

            if ($result['status'] === 'success') {
                // Store payment and subscription data in session
                $request->getSession()->set('payment_amount', $data['amount']);

                // Store subscription data if subscription is selected
                if ($data['subscribeAndCheckout'] && $data['plan']) {
                    $subscriptionData = [
                        'plan_id' => $data['plan']->getId(),
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
                    $request->getSession()->set('subscription_data', $subscriptionData);
                } else {
                    $request->getSession()->remove('subscription_data');
                }

                // Create Step 2 form with the NMI form URL as action
                $step2Form = $this->createForm(Step2Type::class, null, [
                    'action' => $result['form_url']
                ]);

                // Render Step 2 form
                return $this->render('payment/step2.html.twig', [
                    'step2Form' => $step2Form->createView(),
                    'amount' => $data['amount']
                ]);
            } else {
                $this->addFlash('danger', 'Failed to initialize payment: ' . $result['message']);
                $this->logger->error('Step 1 failed', ['message' => $result['message']]);
            }
        }

        return $this->render('payment/checkout.html.twig', [
            'checkoutForm' => $form->createView(),
        ]);
    }

    #[Route('/refund', name: 'app_refund')]
    public function refund(Request $request)
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
                // Cancel related subscriptions
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

    #[Route('/api/transactions', name: 'app_payment_history')]
    public function getTransactions(Request $request, PaymentTransactionRepository $repository)
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

    private function createSubscriptionFromPayment(array $subscriptionData, Request $request, string $transactionId): void
    {
        // Find the plan
        $plan = $this->planRepository->find($subscriptionData['plan_id']);
        if (!$plan) {
            throw new \Exception('Plan not found: ' . $subscriptionData['plan_id']);
        }

        // Create subscription with default start date (tomorrow)
        $startDate = new \DateTime('+1 day');

        // Prepare subscription data for DTO
        $formData = [
            'plan' => $plan,
            'start_date' => $startDate,
            'customer_email' => $subscriptionData['customer_email'],
            'billingFirstName' => $subscriptionData['billing_first_name'],
            'billingLastName' => $subscriptionData['billing_last_name'],
            'billingAddress1' => $subscriptionData['billing_address1'],
            'billingAddress2' => $subscriptionData['billing_address2'],
            'billingCity' => $subscriptionData['billing_city'],
            'billingState' => $subscriptionData['billing_state'],
            'billingPostal' => $subscriptionData['billing_postal'],
            'billingCountry' => $subscriptionData['billing_country'],
            'billingPhone' => $subscriptionData['billing_phone'],
            'original_transaction_id' => $transactionId,
        ];

        // Create DTO from form data
        $subscriptionDto = CreateSubscriptionDto::fromFormData($formData);

        // Create subscription via NMI using DTO
        $result = $this->paymentGateway->createSubscriptionWithPlan($subscriptionDto);

        if ($result['status'] === 'success') {
            $this->addFlash('success', 'Subscription created successfully! You will be charged ' .
                '$' . number_format($plan->getAmount(), 2) . ' ' . $plan->getFrequency() .
                ' starting ' . $startDate->format('M j, Y'));

            $this->logger->info('Subscription created after successful payment', [
                'subscription_id' => $result['subscription_id'] ?? 'N/A',
                'local_subscription_id' => $result['local_subscription_id'] ?? 'N/A',
                'plan_id' => $subscriptionDto->getPlanId(),
                'customer_email' => $subscriptionDto->customerEmail,
                'created_after_payment' => true
            ]);
        } else {
            throw new \Exception('Subscription creation failed: ' . $result['message']);
        }
    }

    private function cancelSubscriptionsByTransactionId(string $transactionId): string
    {
        // Find active subscriptions linked to this transaction
        $subscriptions = $this->subscriptionRepository->findByOriginalTransactionId($transactionId);
        
        if (empty($subscriptions)) {
            return '';
        }

        $cancelledCount = 0;
        $cancelMessage = '';

        foreach ($subscriptions as $subscription) {
            try {
                // Cancel subscription via NMI
                $result = $this->paymentGateway->cancelSubscription($subscription->getSubscriptionId());
                
                if ($result['status'] === 'success') {
                    // Update subscription status in database
                    $this->subscriptionRepository->cancelSubscription($subscription, true);
                    $cancelledCount++;
                    
                    $this->logger->info('Subscription cancelled due to refund', [
                        'subscription_id' => $subscription->getSubscriptionId(),
                        'local_subscription_id' => $subscription->getId(),
                        'original_transaction_id' => $transactionId,
                        'customer_email' => $subscription->getCustomerEmail(),
                        'refund_triggered' => true
                    ]);
                } else {
                    $this->logger->error('Failed to cancel subscription via NMI during refund', [
                        'subscription_id' => $subscription->getSubscriptionId(),
                        'local_subscription_id' => $subscription->getId(),
                        'original_transaction_id' => $transactionId,
                        'nmi_error' => $result['message'] ?? 'Unknown error',
                        'refund_triggered' => true
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Exception while cancelling subscription during refund', [
                    'subscription_id' => $subscription->getSubscriptionId(),
                    'local_subscription_id' => $subscription->getId(),
                    'original_transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                    'refund_triggered' => true
                ]);
            }
        }

        if ($cancelledCount > 0) {
            $cancelMessage = sprintf(' %d subscription(s) have been automatically cancelled.', $cancelledCount);
        } elseif (count($subscriptions) > 0) {
            $cancelMessage = ' Warning: Some subscriptions could not be cancelled automatically. Please check manually.';
        }

        return $cancelMessage;
    }
}
