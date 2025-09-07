<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Form\SubscriptionType;
use App\Repository\SubscriptionRepository;
use App\Repository\PlanRepository;
use App\Service\NmiPaymentGateway;
use App\Dto\CreateSubscriptionDto;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly NmiPaymentGateway $paymentGateway,
        private readonly LoggerInterface $logger,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PlanRepository $planRepository,
    ) {

    }

    #[Route('/subscription/create', name: 'app_subscription_create')]
    public function createSubscription(Request $request): Response
    {
        $activePlans = $this->planRepository->findActivePlans();

        $form = $this->createForm(SubscriptionType::class, null, [
            'active_plans' => $activePlans
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $subscriptionDto = CreateSubscriptionDto::fromFormData($data);
            $result = $this->paymentGateway->createSubscriptionWithPlan($subscriptionDto);

            if ($result['status'] === 'success') {
                $this->addFlash('success', $result['message']);
                $this->logger->info('Subscription created successfully', [
                    'subscription_id' => $result['subscription_id'] ?? 'N/A',
                    'local_subscription_id' => $result['local_subscription_id'] ?? 'N/A',
                    'plan_id' => $subscriptionDto->getPlanId(),
                    'customer_email' => $subscriptionDto->customerEmail
                ]);

                return $this->redirectToRoute('app_subscription_list');
            } else {
                $this->addFlash('danger', 'Failed to create subscription: ' . $result['message']);
                $this->logger->error('Subscription creation failed', [
                    'message' => $result['message'],
                    'plan_id' => $subscriptionDto->getPlanId(),
                    'customer_email' => $subscriptionDto->customerEmail
                ]);
            }
        }

        return $this->render('subscription/create.html.twig', [
            'subscriptionForm' => $form,
        ]);
    }

    #[Route('/subscriptions', name: 'app_subscription_list')]
    public function listSubscriptions(SubscriptionRepository $subscriptionRepository): Response
    {
        $subscriptions = $subscriptionRepository->findBy([], ['created_at' => 'DESC']);

        return $this->render('subscription/list.html.twig', [
            'subscriptions' => $subscriptions,
        ]);
    }

    #[Route('/subscription/{id}/cancel', name: 'app_subscription_cancel', methods: ['POST'])]
    public function cancelSubscription(Subscription $subscription): Response
    {
        $result = $this->paymentGateway->cancelSubscription($subscription->getSubscriptionId());

        if ($result['status'] === 'success') {

            $this->subscriptionRepository->cancelSubscription($subscription, true);

            if (isset($result['already_cancelled']) && $result['already_cancelled']) {
                $this->addFlash('info', 'Subscription was already cancelled. Local status has been synchronized.');
                $this->logger->info('Subscription status synchronized', [
                    'subscription_id' => $subscription->getSubscriptionId(),
                    'local_id' => $subscription->getId(),
                    'note' => 'Was already cancelled in NMI'
                ]);
            } else {
                $this->addFlash('success', 'Subscription cancelled successfully');
                $this->logger->info('Subscription cancelled', [
                    'subscription_id' => $subscription->getSubscriptionId(),
                    'local_id' => $subscription->getId()
                ]);
            }
        } else {
            $this->addFlash('danger', 'Failed to cancel subscription: ' . $result['message']);
            $this->logger->error('Subscription cancellation failed', [
                'subscription_id' => $subscription->getSubscriptionId(),
                'local_id' => $subscription->getId(),
                'message' => $result['message']
            ]);
        }

        return $this->redirectToRoute('app_subscription_list');
    }

    #[Route('/subscription/{id}/rebill', name: 'app_subscription_rebill', methods: ['POST'])]
    public function processRebilling(Subscription $subscription, Request $request): Response
    {
        $customAmount = $request->request->get('amount');
        $amount = $customAmount ? (float) $customAmount : $subscription->getAmount();

        $result = $this->paymentGateway->processRebilling(
            $subscription->getSubscriptionId(),
            $subscription->getCustomerVaultId(),
            $amount
        );

        if ($result['status'] === 'success') {
            // Update next charge date based on frequency
            $nextChargeDate = clone $subscription->getNextChargeDate();
            match($subscription->getFrequency()) {
                'weekly' => $nextChargeDate->add(new \DateInterval('P7D')),
                'monthly' => $nextChargeDate->add(new \DateInterval('P1M')),
                'yearly' => $nextChargeDate->add(new \DateInterval('P1Y')),
            };

            $this->subscriptionRepository->updateAfterRebilling($subscription, $nextChargeDate, true);

            $this->addFlash('success', 'Rebilling processed successfully. Transaction ID: ' . $result['transaction_id']);
            $this->logger->info('Manual rebilling processed', [
                'subscription_id' => $subscription->getSubscriptionId(),
                'local_id' => $subscription->getId(),
                'transaction_id' => $result['transaction_id'],
                'amount' => $amount
            ]);
        } else {
            $this->addFlash('danger', 'Rebilling failed: ' . $result['message']);
            $this->logger->error('Manual rebilling failed', [
                'subscription_id' => $subscription->getSubscriptionId(),
                'local_id' => $subscription->getId(),
                'message' => $result['message']
            ]);
        }

        return $this->redirectToRoute('app_subscription_list');
    }

    #[Route('/api/subscriptions', name: 'app_api_subscription_create', methods: ['POST'])]
    public function createSubscriptionApi(Request $request): Response
    {
        // Validate Content-Type
        if (!$request->headers->contains('Content-Type', 'application/json')) {
            return new Response(
                json_encode(['error' => 'Content-Type must be application/json']),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new Response(
                json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        $requiredFields = [
            'plan_id',
            'customer_email',
            'billing_first_name',
            'billing_last_name',
            'billing_postal',
            'billing_country'
        ];

        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            return new Response(
                json_encode([
                    'error' => 'Missing required fields',
                    'missing_fields' => $missingFields
                ]),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        try {
            $plan = $this->planRepository->findOneBy(['planId' => $data['plan_id'], 'status' => 'active']);

            if (!$plan) {
                return new Response(
                    json_encode(['error' => 'Plan not found or inactive: ' . $data['plan_id']]),
                    404,
                    ['Content-Type' => 'application/json']
                );
            }

            // Validate email format
            if (!filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
                return new Response(
                    json_encode(['error' => 'Invalid email format']),
                    400,
                    ['Content-Type' => 'application/json']
                );
            }

            // Parse start date (default to tomorrow if not provided)
            $startDate = new \DateTime('+1 day');
            if (!empty($data['start_date'])) {
                try {
                    $startDate = new \DateTime($data['start_date']);
                } catch (\Exception $e) {
                    return new Response(
                        json_encode(['error' => 'Invalid start_date format. Use YYYY-MM-DD']),
                        400,
                        ['Content-Type' => 'application/json']
                    );
                }
            }

            // Prepare subscription data for DTO
            $subscriptionData = [
                'plan' => $plan,
                'start_date' => $startDate,
                'customer_email' => $data['customer_email'],
                'billingFirstName' => $data['billing_first_name'],
                'billingLastName' => $data['billing_last_name'],
                'billingAddress1' => $data['billing_address1'] ?? null,
                'billingAddress2' => $data['billing_address2'] ?? null,
                'billingCity' => $data['billing_city'] ?? null,
                'billingState' => $data['billing_state'] ?? null,
                'billingPostal' => $data['billing_postal'],
                'billingCountry' => $data['billing_country'],
                'billingPhone' => $data['billing_phone'] ?? null,
            ];

            $subscriptionDto = CreateSubscriptionDto::fromFormData($subscriptionData);
            $result = $this->paymentGateway->createSubscriptionWithPlan($subscriptionDto);

            if ($result['status'] === 'success') {
                $this->logger->info('Subscription created successfully via API', [
                    'subscription_id' => $result['subscription_id'] ?? 'N/A',
                    'local_subscription_id' => $result['local_subscription_id'] ?? 'N/A',
                    'plan_id' => $subscriptionDto->getPlanId(),
                    'customer_email' => $subscriptionDto->customerEmail,
                    'api_request' => true
                ]);

                return new Response(
                    json_encode([
                        'success' => true,
                        'message' => 'Subscription created successfully',
                        'subscription_id' => $result['subscription_id'] ?? null,
                        'local_subscription_id' => $result['local_subscription_id'] ?? null,
                        'plan_id' => $subscriptionDto->getPlanId(),
                        'plan_name' => $plan->getPlanName(),
                        'amount' => $subscriptionDto->getAmount(),
                        'frequency' => $subscriptionDto->getFrequency(),
                        'start_date' => $startDate->format('Y-m-d'),
                        'next_charge_date' => $subscriptionDto->getNextChargeDate()->format('Y-m-d'),
                        'customer_email' => $subscriptionDto->customerEmail
                    ], JSON_PRETTY_PRINT),
                    201,
                    ['Content-Type' => 'application/json']
                );
            } else {
                $this->logger->error('Subscription creation failed via API', [
                    'message' => $result['message'],
                    'plan_id' => $subscriptionDto->getPlanId(),
                    'customer_email' => $subscriptionDto->customerEmail,
                    'api_request' => true
                ]);

                return new Response(
                    json_encode([
                        'success' => false,
                        'error' => 'Failed to create subscription',
                        'message' => $result['message'],
                        'plan_id' => $subscriptionDto->getPlanId()
                    ], JSON_PRETTY_PRINT),
                    422,
                    ['Content-Type' => 'application/json']
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Subscription API creation exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $data
            ]);

            return new Response(
                json_encode([
                    'success' => false,
                    'error' => 'Internal server error',
                    'message' => 'An unexpected error occurred while creating the subscription'
                ], JSON_PRETTY_PRINT),
                500,
                ['Content-Type' => 'application/json']
            );
        }
    }

    #[Route('/api/subscriptions', name: 'app_api_subscriptions', methods: ['GET'])]
    public function getSubscriptions(SubscriptionRepository $subscriptionRepository): Response
    {
        $subscriptions = $subscriptionRepository->findBy([], ['created_at' => 'DESC']);
        $subscriptionsData = [];

        foreach ($subscriptions as $subscription) {
            $subscriptionsData[] = [
                'id' => $subscription->getId(),
                'subscription_id' => $subscription->getSubscriptionId(),
                'customer_email' => $subscription->getCustomerEmail(),
                'amount' => $subscription->getAmount(),
                'currency_code' => $subscription->getCurrencyCode(),
                'frequency' => $subscription->getFrequency(),
                'status' => $subscription->getStatus(),
                'start_date' => $subscription->getStartDate()?->format('Y-m-d'),
                'next_charge_date' => $subscription->getNextChargeDate()?->format('Y-m-d'),
                'created_at' => $subscription->getCreatedAt()?->format('Y-m-d H:i:s'),
                'plan' => $subscription->getPlan() ? [
                    'id' => $subscription->getPlan()->getId(),
                    'plan_id' => $subscription->getPlan()->getPlanId(),
                    'plan_name' => $subscription->getPlan()->getPlanName(),
                ] : null,
            ];
        }

        return new Response(
            json_encode($subscriptionsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
