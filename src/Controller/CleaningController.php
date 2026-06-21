<?php

namespace App\Controller;

use App\Entity\CleaningRecord;
use App\Entity\Dress;
use App\Service\CleaningService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cleaning')]
class CleaningController extends AbstractController
{
    #[Route('/', name: 'app_cleaning_index', methods: ['GET'])]
    public function index(CleaningService $cleaningService): Response
    {
        return $this->render('cleaning/index.html.twig', [
            'pending' => $cleaningService->getPendingList(),
            'inProgress' => $cleaningService->getInProgressList(),
        ]);
    }

    #[Route('/schedule/{dressId}', name: 'app_cleaning_schedule', methods: ['POST'])]
    public function schedule(
        Request $request,
        int $dressId,
        CleaningService $cleaningService,
        EntityManagerInterface $em,
    ): Response {
        $dress = $em->getRepository(Dress::class)->find($dressId);
        if (!$dress) {
            throw $this->createNotFoundException('服装不存在');
        }

        if (!$this->isCsrfTokenValid('schedule' . $dressId, $request->request->get('_token'))) {
            $this->addFlash('error', '无效的安全令牌');
            return $this->redirectToRoute('app_dress_show', ['id' => $dressId]);
        }

        try {
            $record = $cleaningService->scheduleCleaning($dress);
            $dress->setStatus(Dress::STATUS_CLEANING);
            $em->persist($dress);
            $em->flush();
            $this->addFlash('success', '已安排清洗，记录编号：#' . $record->getId());
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_cleaning_index');
    }

    #[Route('/start/{id}', name: 'app_cleaning_start', methods: ['POST'])]
    public function start(
        Request $request,
        CleaningRecord $record,
        CleaningService $cleaningService,
    ): Response {
        if (!$this->isCsrfTokenValid('start' . $record->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', '无效的安全令牌');
            return $this->redirectToRoute('app_cleaning_index');
        }

        try {
            $cleaningService->startCleaning($record);
            $this->addFlash('success', '已开始清洗');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_cleaning_index');
    }

    #[Route('/complete/{id}', name: 'app_cleaning_complete', methods: ['POST'])]
    public function complete(
        Request $request,
        CleaningRecord $record,
        CleaningService $cleaningService,
    ): Response {
        if (!$this->isCsrfTokenValid('complete' . $record->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', '无效的安全令牌');
            return $this->redirectToRoute('app_cleaning_index');
        }

        try {
            $cleaningService->completeCleaning($record);
            $this->addFlash('success', '清洗完成，服装已可出租！');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_cleaning_index');
    }

    #[Route('/mark-ready/{dressId}', name: 'app_cleaning_mark_ready', methods: ['POST'])]
    public function markReady(
        Request $request,
        int $dressId,
        CleaningService $cleaningService,
        EntityManagerInterface $em,
    ): Response {
        $dress = $em->getRepository(Dress::class)->find($dressId);
        if (!$dress) {
            throw $this->createNotFoundException('服装不存在');
        }

        if (!$this->isCsrfTokenValid('markready' . $dressId, $request->request->get('_token'))) {
            $this->addFlash('error', '无效的安全令牌');
            return $this->redirectToRoute('app_dress_show', ['id' => $dressId]);
        }

        try {
            $cleaningService->markDamagedDressReady($dress);
            $this->addFlash('success', '损坏已修复，已安排清洗');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_dress_show', ['id' => $dressId]);
    }
}
