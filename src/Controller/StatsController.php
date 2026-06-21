<?php

namespace App\Controller;

use App\Service\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/stats')]
class StatsController extends AbstractController
{
    #[Route('/', name: 'app_stats_index', methods: ['GET'])]
    public function index(Request $request, StatsService $statsService): Response
    {
        $year = (int)$request->query->get('year', date('Y'));
        $month = (int)$request->query->get('month', date('n'));

        $stats = $statsService->getMonthlyStats($year, $month);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = $m;
        }

        $years = [];
        $currentYear = (int)date('Y');
        for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++) {
            $years[] = $y;
        }

        return $this->render('stats/index.html.twig', [
            'stats' => $stats,
            'months' => $months,
            'years' => $years,
            'selectedYear' => $year,
            'selectedMonth' => $month,
        ]);
    }
}
