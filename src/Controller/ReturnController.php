<?php

namespace App\Controller;

use App\Entity\Rental;
use App\Repository\RentalRepository;
use App\Service\ReturnService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/return')]
class ReturnController extends AbstractController
{
    #[Route('/', name: 'app_return_index', methods: ['GET'])]
    public function index(RentalRepository $rentalRepository): Response
    {
        $activeRentals = $rentalRepository->findActive();

        return $this->render('return/index.html.twig', [
            'rentals' => $activeRentals,
        ]);
    }

    #[Route('/process/{id}', name: 'app_return_process', methods: ['GET', 'POST'])]
    public function process(
        Request $request,
        Rental $rental,
        ReturnService $returnService,
    ): Response {
        if ($request->isMethod('POST')) {
            $returnDateStr = $request->request->get('return_date', date('Y-m-d'));
            $needsCleaning = $request->request->getBoolean('needs_cleaning', true);
            $damageItems = [];

            $descriptions = $request->request->all('damage_description');
            $amounts = $request->request->all('damage_amount');

            if (is_array($descriptions)) {
                foreach ($descriptions as $idx => $desc) {
                    if (!empty($desc) && isset($amounts[$idx]) && (float)$amounts[$idx] > 0) {
                        $damageItems[] = [
                            'description' => trim($desc),
                            'amount' => (float)$amounts[$idx],
                        ];
                    }
                }
            }

            try {
                $returnDate = new \DateTime($returnDateStr);
                $updatedRental = $returnService->processReturn(
                    $rental,
                    $returnDate,
                    $damageItems,
                    $needsCleaning
                );
                $this->addFlash('success', sprintf(
                    '归还登记成功！押金 ¥%s，扣款 ¥%s，退还 ¥%s',
                    $updatedRental->getDepositPaid(),
                    $updatedRental->getDamageDeduction() ?? '0.00',
                    $updatedRental->getDepositRefunded() ?? '0.00'
                ));
                return $this->redirectToRoute('app_rental_show', ['id' => $updatedRental->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('return/process.html.twig', [
            'rental' => $rental,
            'today' => date('Y-m-d'),
        ]);
    }

    #[Route('/close/{id}', name: 'app_return_close', methods: ['POST'])]
    public function close(
        Request $request,
        Rental $rental,
        ReturnService $returnService,
    ): Response {
        if (!$this->isCsrfTokenValid('close' . $rental->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', '无效的安全令牌');
            return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()]);
        }

        try {
            $returnService->closeRental($rental);
            $this->addFlash('success', '租单已结单！');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()]);
    }
}
