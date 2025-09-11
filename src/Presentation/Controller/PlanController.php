<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Command\CreatePlanCommand;
use App\Application\Command\TogglePlanStatusCommand;
use App\Application\Handler\CreatePlanCommandHandler;
use App\Application\Handler\TogglePlanStatusCommandHandler;
use App\Domain\Billing\Repository\PlanRepositoryInterface;
use App\Domain\Billing\ValueObject\PlanId;
use App\Presentation\Form\PlanType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/plan', name: 'app_plan_')]
final class PlanController extends AbstractController
{
    public function __construct(
        private readonly CreatePlanCommandHandler $createPlanHandler,
        private readonly TogglePlanStatusCommandHandler $toggleStatusHandler,
        private readonly PlanRepositoryInterface $planRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('s', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('plan/list.html.twig', [
            'plans' => $this->planRepository->findAll(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $form = $this->createForm(PlanType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $command = new CreatePlanCommand(
                    planName: $data['planName'],
                    amount: (float) $data['amount'],
                    currencyCode: 'USD',
                    frequency: $data['frequency']
                );

                $result = $this->createPlanHandler->handle($command);

                $this->addFlash('success', sprintf(
                    'Plan created successfully! Name: %s, Amount: %s',
                    $result->planName,
                    $result->amount
                ));

                $this->logger->info('Plan created via DDD', [
                    'plan_id' => $result->planId,
                    'plan_name' => $result->planName,
                    'amount' => $result->amount
                ]);

                return $this->redirectToRoute('app_plan_index');

            } catch (\Exception $e) {
                $this->addFlash('danger', 'Plan creation failed: ' . $e->getMessage());
                $this->logger->error('Plan creation error', [
                    'error' => $e->getMessage(),
                    'plan_name' => $data['planName'],
                    'amount' => $data['amount']
                ]);
            }
        }

        return $this->render('plan/create.html.twig', [
            'planForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(string $id): Response
    {
        try {
            // Create and execute the command using DDD approach
            $command = new TogglePlanStatusCommand(
                planId: $id
            );

            $result = $this->toggleStatusHandler->handle($command);

            // Add success flash message
            $this->addFlash('success', $result['message']); // Keep as array for now - we'll update when we refactor TogglePlanStatusCommandHandler

            return $this->redirectToRoute('app_plan_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Status toggle failed: ' . $e->getMessage());
            
            return $this->redirectToRoute('app_plan_index');
        }
    }

    #[Route('/api/create', name: 'api_create', methods: ['POST'])]
    public function apiCreate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            // Validate required fields
            $requiredFields = ['planName', 'amount', 'frequency', 'dayFrequency'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
                    return new JsonResponse(['error' => "Field '{$field}' is required"], 400);
                }
            }

            // Create plan using DDD command
            $command = new CreatePlanCommand(
                planName: $data['planName'],
                amount: (float) $data['amount'],
                currencyCode: $data['currencyCode'] ?? 'USD',
                frequency: $data['frequency']
            );

            $result = $this->createPlanHandler->handle($command);

            $this->logger->info('Plan created via API', [
                'plan_id' => $result->planId,
                'plan_name' => $result->planName
            ]);

            return new JsonResponse([
                'success' => true,
                'plan' => [
                    'plan_id' => $result->planId,
                    'plan_name' => $result->planName,
                    'amount' => $result->amount,
                    'frequency' => $result->frequency,
                    'day_frequency' => $result->dayFrequency,
                    'status' => $result->status,
                    'nmi_result' => $result->nmiResult,
                ]
            ], 201);

        } catch (\JsonException $e) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);
        } catch (\Exception $e) {
            $this->logger->error('API plan creation error', [
                'error' => $e->getMessage(),
                'data' => $data ?? null
            ]);

            return new JsonResponse([
                'error' => 'Plan creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
