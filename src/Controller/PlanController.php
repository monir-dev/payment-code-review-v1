<?php

namespace App\Controller;

use App\Entity\Plan;
use App\Form\PlanType;
use App\Repository\PlanRepository;
use App\Service\NmiPaymentGateway;
use App\Dto\CreatePlanDto;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlanController extends AbstractController
{
    public function __construct(
        private readonly NmiPaymentGateway $paymentGateway,
        private readonly LoggerInterface $logger,
        private readonly PlanRepository $planRepository
    ) {

    }

    #[Route('/plan/create', name: 'app_plan_create')]
    public function createPlan(Request $request): Response
    {
        $form = $this->createForm(PlanType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $planDto = CreatePlanDto::fromFormData($form->getData());
            $nmiResult = $this->paymentGateway->createPlan($planDto);

            if ($nmiResult['status'] === 'success') {
                try {
                    $plan = $this->planRepository->createFromDto($planDto);

                    $this->addFlash('success', sprintf('Plan "%s" created successfully! Plan ID: %s', $planDto->planName, $planDto->planId));
                    $this->logger->info('Plan created and saved successfully', [
                        'plan_name' => $planDto->planName,
                        'nmi_plan_id' => $planDto->planId,
                        'local_plan_id' => $plan->getId(),
                        'amount' => $planDto->amount,
                        'frequency' => $planDto->frequency
                    ]);

                    return $this->redirectToRoute('app_plan_list');
                } catch (\Exception $dbException) {
                    $this->addFlash('danger', 'Plan created with NMI but failed to save locally: ' . $dbException->getMessage());
                    $this->logger->error('Plan database save failed after NMI creation', [
                        'plan_name' => $planDto->planName,
                        'nmi_plan_id' => $planDto->planId,
                        'db_error' => $dbException->getMessage()
                    ]);
                }
            } else {
                $this->addFlash('danger', 'Plan creation failed: ' . $nmiResult['message']);
                $this->logger->error('NMI plan creation failed', [
                    'plan_name' => $planDto->planName,
                    'amount' => $planDto->amount,
                    'frequency' => $planDto->frequency,
                    'error' => $nmiResult['message']
                ]);
            }
        }

        return $this->render('plan/create.html.twig', [
            'planForm' => $form,
        ]);
    }

    #[Route('/plans', name: 'app_plan_list')]
    public function listPlans(): Response
    {
        $plans = $this->planRepository->findBy([], ['created_at' => 'DESC']);

        return $this->render('plan/list.html.twig', [
            'plans' => $plans
        ]);
    }

    #[Route('/plan/{id}/toggle-status', name: 'app_plan_toggle_status', methods: ['POST'])]
    public function togglePlanStatus(Plan $plan): Response
    {
        $oldStatus = $plan->getStatus();
        $newStatus = $this->planRepository->toggleStatus($plan);

        if ($newStatus === 'active') {
            $this->addFlash('success', sprintf('Plan "%s" has been activated', $plan->getPlanName()));
            $this->logger->info('Plan activated', ['plan_id' => $plan->getPlanId(), 'old_status' => $oldStatus, 'new_status' => $newStatus]);
        } else {
            $this->addFlash('warning', sprintf('Plan "%s" has been deactivated', $plan->getPlanName()));
            $this->logger->info('Plan deactivated', ['plan_id' => $plan->getPlanId(), 'old_status' => $oldStatus, 'new_status' => $newStatus]);
        }

        return $this->redirectToRoute('app_plan_list');
    }

}
