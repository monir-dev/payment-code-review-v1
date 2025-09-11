<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Command\CreateSubscriptionCommand;
use App\Application\Command\CancelSubscriptionCommand;
use App\Application\Command\RebillSubscriptionCommand;
use App\Application\Handler\CreateSubscriptionCommandHandler;
use App\Application\Handler\CancelSubscriptionCommandHandler;
use App\Application\Handler\RebillSubscriptionCommandHandler;
use App\Domain\Billing\Repository\PlanRepositoryInterface;
use App\Domain\Subscription\Repository\SubscriptionRepositoryInterface;
use App\Presentation\Form\SubscriptionType;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/subscription', name: 'app_subscription_')]
final class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly CreateSubscriptionCommandHandler $createSubscriptionHandler,
        private readonly CancelSubscriptionCommandHandler $cancelSubscriptionHandler,
        private readonly RebillSubscriptionCommandHandler $rebillSubscriptionHandler,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly PlanRepositoryInterface $planRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('s', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('subscription/list.html.twig', [
            'subscriptions' => $this->subscriptionRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $activePlans = $this->planRepository->findActivePlans();

        $form = $this->createForm(SubscriptionType::class, null, [
            'active_plans' => $activePlans
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $result = $this->createSubscriptionHandler->handle(
                    new CreateSubscriptionCommand(
                        planId: $data['plan_id'],
                        customerEmail: $data['customer_email'],
                        firstName: $data['billing_first_name'],
                        lastName: $data['billing_last_name'],
                        street1: $data['billing_address1'],
                        street2: $data['billing_address2'] ?? null,
                        city: $data['billing_city'],
                        state: $data['billing_state'],
                        postalCode: $data['billing_postal'],
                        country: $data['billing_country'],
                        phone: $data['billing_phone'] ?? null,
                        startDate: $data['start_date'] ?? new DateTimeImmutable()
                    )
                );

                $this->addFlash('success', sprintf(
                    'Subscription created successfully! ID: %s, Next charge: %s',
                    $result->subscriptionId,
                    $result->nextChargeDate
                ));

                $this->logger->info('Subscription created via DDD', [
                    'subscription_id' => $result->subscriptionId,
                    'plan_name' => $result->planName,
                    'amount' => $result->amount->format()
                ]);

                return $this->redirectToRoute('app_subscription_index');

            } catch (Exception $e) {
                $this->addFlash('danger', 'Subscription creation failed: ' . $e->getMessage());
                $this->logger->error('Subscription creation error', [
                    'error' => $e->getMessage(),
                    'plan_id' => $data['plan_id'],
                    'customer_email' => $data['customer_email']
                ]);
            }
        }

        return $this->render('subscription/create.html.twig', [
            'subscriptionForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(string $id, Request $request): Response
    {
        try {
            $result = $this->cancelSubscriptionHandler->handle(
                new CancelSubscriptionCommand(
                    subscriptionId: $id,
                    reason: $request->request->get('reason', 'Customer request'),
                    cancelWithGateway: $request->request->getBoolean('cancel_with_gateway', true),
                    cancelledBy: $this->getUser()?->getUserIdentifier()
                )
            );

            $this->addFlash('success', sprintf(
                'Subscription cancelled successfully! Status: %s',
                $result->newStatus
            ));

            $this->logger->info('Subscription cancelled via DDD command', [
                'subscription_id' => $result->subscriptionId,
                'reason' => $result->reason,
                'gateway_cancelled' => $result->gatewayProcessed
            ]);

            return $this->redirectToRoute('app_subscription_index');

        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', 'Cancellation failed: ' . $e->getMessage());
            $this->logger->warning('Subscription cancellation validation error', [
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            $this->addFlash('error', 'Cancellation failed due to unexpected error');
            $this->logger->error('Subscription cancellation error', [
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);
        }

        return $this->redirectToRoute('app_subscription_index');
    }

    #[Route('/{id}/rebill', name: 'rebill', methods: ['POST'])]
    public function rebill(string $id, Request $request): Response
    {
        try {
            $customAmount = $request->request->get('amount');
            $amount = $customAmount ? (float) $customAmount : null;

            $result = $this->rebillSubscriptionHandler->handle(
                new RebillSubscriptionCommand(
                    subscriptionId: $id,
                    customAmount: $amount,
                    processWithGateway: $request->request->getBoolean('process_with_gateway', true),
                    reason: $request->request->get('reason', 'Manual rebill'),
                    initiatedBy: $this->getUser()?->getUserIdentifier()
                )
            );

            $this->addFlash('success', sprintf(
                'Subscription rebilled successfully! Transaction ID: %s, Next charge: %s',
                $result->transactionId,
                $result->nextChargeDate
            ));

            $this->logger->info('Subscription rebilled via DDD command', [
                'subscription_id' => $result->subscriptionId,
                'transaction_id' => $result->transactionId,
                'amount' => $result->amount,
                'gateway_processed' => $result->gatewayProcessed
            ]);

            return $this->redirectToRoute('app_subscription_index');

        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', 'Rebill failed: ' . $e->getMessage());
            $this->logger->warning('Subscription rebill validation error', [
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            $this->addFlash('error', 'Rebill failed due to unexpected error');
            $this->logger->error('Subscription rebill error', [
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);
        }

        return $this->redirectToRoute('app_subscription_index');
    }

    #[Route('/api/create', name: 'api_create', methods: ['POST'])]
    public function apiCreate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $requiredFields = ['planId', 'customerEmail', 'firstName', 'lastName', 'street1', 'city', 'state', 'postalCode', 'country'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return new JsonResponse(['error' => "Field '{$field}' is required"], 400);
                }
            }

            $result = $this->createSubscriptionHandler->handle(
                new CreateSubscriptionCommand(
                    planId: $data['planId'],
                    customerEmail: $data['customerEmail'],
                    firstName: $data['firstName'],
                    lastName: $data['lastName'],
                    street1: $data['street1'],
                    street2: $data['street2'] ?? null,
                    city: $data['city'],
                    state: $data['state'],
                    postalCode: $data['postalCode'],
                    country: $data['country'],
                    phone: $data['phone'] ?? null,
                    startDate: isset($data['startDate']) ? new DateTimeImmutable($data['startDate']) : new DateTimeImmutable()
                )
            );

            $this->logger->info('Subscription created via API', [
                'subscription_id' => $result->subscriptionId,
                'plan_name' => $result->planName
            ]);

            return new JsonResponse([
                'success' => true,
                'subscription' => [
                    'subscription_id' => $result->subscriptionId,
                    'status' => $result->status,
                    'customer_vault_id' => $result->customerVaultId,
                    'customer_email' => $result->customerEmail,
                    'plan_id' => $result->planId,
                    'plan_name' => $result->planName,
                    'amount' => $result->amount->getAmount(),
                    'frequency' => $result->frequency,
                    'start_date' => $result->startDate,
                    'next_charge_date' => $result->nextChargeDate
                ]
            ], 201);

        } catch (JsonException $e) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);
        } catch (Exception $e) {
            $this->logger->error('API subscription creation error', [
                'error' => $e->getMessage(),
                'data' => $data ?? null
            ]);

            return new JsonResponse([
                'error' => 'Subscription creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{id}/cancel', name: 'api_cancel', methods: ['POST'])]
    public function apiCancel(string $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            // Create cancellation command
            $command = new CancelSubscriptionCommand(
                subscriptionId: $id,
                reason: $data['reason'] ?? 'Customer request',
                cancelWithGateway: $data['cancelWithGateway'] ?? true,
                cancelledBy: $data['cancelledBy'] ?? null
            );

            // Execute cancellation using DDD command handler
            $result = $this->cancelSubscriptionHandler->handle($command);

            $this->logger->info('Subscription cancelled via API', [
                'subscription_id' => $result->subscriptionId,
                'reason' => $result->reason,
                'cancelled_by' => $command->cancelledBy
            ]);

            return new JsonResponse([
                'success' => true,
                'subscription' => [
                    'subscription_id' => $result->subscriptionId,
                    'status' => $result->newStatus,
                    'reason' => $result->reason,
                    'cancelled_at' => $result->cancelledAt,
                    'gateway_processed' => $result->gatewayProcessed
                ]
            ], 200);

        } catch (JsonException $e) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);

        } catch (InvalidArgumentException $e) {
            $this->logger->warning('API subscription cancellation validation error', [
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Validation failed: ' . $e->getMessage()
            ], 400);

        } catch (Exception $e) {
            $this->logger->error('API subscription cancellation error', [
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Subscription cancellation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{id}/rebill', name: 'api_rebill', methods: ['POST'])]
    public function apiRebill(string $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $command = new RebillSubscriptionCommand(
                subscriptionId: $id,
                customAmount: $data['customAmount'] ?? null,
                processWithGateway: $data['processWithGateway'] ?? true,
                reason: $data['reason'] ?? 'Manual rebill',
                initiatedBy: $data['initiatedBy'] ?? null
            );

            $result = $this->rebillSubscriptionHandler->handle($command);

            $this->logger->info('Subscription rebilled via API', [
                'subscription_id' => $result->subscriptionId,
                'transaction_id' => $result->transactionId,
                'amount' => $result->amount,
                'initiated_by' => $command->initiatedBy
            ]);

            return new JsonResponse([
                'success' => true,
                'subscription' => [
                    'subscription_id' => $result->subscriptionId,
                    'status' => $result->status,
                    'transaction_id' => $result->transactionId,
                    'amount' => $result->amount,
                    'reason' => $result->reason,
                    'next_charge_date' => $result->nextChargeDate,
                    'rebilled_at' => $result->rebilledAt,
                    'gateway_processed' => $result->gatewayProcessed
                ]
            ], 200);

        } catch (JsonException $e) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);

        } catch (InvalidArgumentException $e) {
            $this->logger->warning('API subscription rebill validation error', [
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Validation failed: ' . $e->getMessage()
            ], 400);

        } catch (Exception $e) {
            $this->logger->error('API subscription rebill error', [
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Subscription rebill failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
