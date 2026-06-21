<?php

namespace App\Controller;

use App\Service\RentalService;
use App\Service\StatsService;
use App\Repository\RentalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        StatsService $statsService,
        RentalRepository $rentalRepository,
        RentalService $rentalService,
    ): Response {
        $rentalService->updateOverdueStatuses();

        $overview = $statsService->getOverview();
        $overdueRentals = $rentalRepository->findOverdue();
        $activeRentals = $rentalRepository->findActive();

        return $this->render('home/index.html.twig', [
            'overview' => $overview,
            'overdueRentals' => $overdueRentals,
            'activeRentals' => array_slice($activeRentals, 0, 10),
        ]);
    }
}
