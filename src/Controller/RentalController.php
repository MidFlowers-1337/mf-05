<?php

namespace App\Controller;

use App\Entity\Dress;
use App\Entity\Rental;
use App\Repository\RentalRepository;
use App\Service\RentalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rental')]
class RentalController extends AbstractController
{
    #[Route('/', name: 'app_rental_index', methods: ['GET'])]
    public function index(RentalRepository $rentalRepository, Request $request, RentalService $rentalService): Response
    {
        $rentalService->updateOverdueStatuses();

        $status = $request->query->get('status');
        if ($status) {
            $rentals = $rentalRepository->findBy(['status' => $status], ['rentalDate' => 'DESC']);
        } else {
            $rentals = $rentalRepository->findBy([], ['rentalDate' => 'DESC']);
        }

        return $this->render('rental/index.html.twig', [
            'rentals' => $rentals,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/overdue', name: 'app_rental_overdue', methods: ['GET'])]
    public function overdue(RentalRepository $rentalRepository, RentalService $rentalService): Response
    {
        $rentalService->updateOverdueStatuses();
        $rentals = $rentalRepository->findOverdue();

        return $this->render('rental/overdue.html.twig', [
            'rentals' => $rentals,
        ]);
    }

    #[Route('/new/{dressId}', name: 'app_rental_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        int $dressId,
        RentalService $rentalService,
        EntityManagerInterface $em,
    ): Response {
        $dress = $em->getRepository(Dress::class)->find($dressId);
        if (!$dress) {
            throw $this->createNotFoundException('服装不存在');
        }

        if ($request->isMethod('POST')) {
            $customerName = $request->request->get('customer_name');
            $customerPhone = $request->request->get('customer_phone');
            $customerIdCard = $request->request->get('customer_idcard');
            $customerAddress = $request->request->get('customer_address');
            $rentalDateStr = $request->request->get('rental_date');
            $dueDateStr = $request->request->get('due_date');
            $notes = $request->request->get('notes');

            if (empty($customerName)) {
                $this->addFlash('error', '请填写客户姓名');
            } else {
                try {
                    $rentalDate = new \DateTime($rentalDateStr);
                    $dueDate = new \DateTime($dueDateStr);
                    $customer = $rentalService->findOrCreateCustomer(
                        $customerName,
                        $customerPhone,
                        $customerIdCard,
                        $customerAddress
                    );
                    $rental = $rentalService->createRental($dress, $customer, $rentalDate, $dueDate, $notes);
                    $this->addFlash('success', sprintf('租单创建成功！单号：#%d', $rental->getId()));
                    return $this->redirectToRoute('app_rental_show', ['id' => $rental->getId()]);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        $today = date('Y-m-d');
        $defaultDue = date('Y-m-d', strtotime('+3 days'));

        return $this->render('rental/new.html.twig', [
            'dress' => $dress,
            'today' => $today,
            'defaultDue' => $defaultDue,
        ]);
    }

    #[Route('/{id}', name: 'app_rental_show', methods: ['GET'])]
    public function show(Rental $rental): Response
    {
        return $this->render('rental/show.html.twig', [
            'rental' => $rental,
        ]);
    }
}
