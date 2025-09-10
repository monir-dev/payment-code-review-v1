<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Handler\GetDashboardStatsQueryHandler;
use App\Application\Query\GetDashboardStatsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly GetDashboardStatsQueryHandler $getDashboardStatsHandler,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $getDashboardStatsQuery = new GetDashboardStatsQuery(
            recentTransactionsLimit: 10,
            activeSubscriptionsLimit: 5
        );

        $dashboardData = $this->getDashboardStatsHandler->handle($getDashboardStatsQuery);

        return $this->render('dashboard/index.html.twig', $dashboardData);
    }
}
