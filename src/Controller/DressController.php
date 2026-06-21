<?php

namespace App\Controller;

use App\Entity\Dress;
use App\Form\DressType;
use App\Repository\DressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dress')]
class DressController extends AbstractController
{
    #[Route('/', name: 'app_dress_index', methods: ['GET'])]
    public function index(DressRepository $dressRepository, Request $request): Response
    {
        $status = $request->query->get('status');
        if ($status) {
            $dresses = $dressRepository->findBy(['status' => $status], ['name' => 'ASC']);
        } else {
            $dresses = $dressRepository->findBy([], ['name' => 'ASC']);
        }

        return $this->render('dress/index.html.twig', [
            'dresses' => $dresses,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/new', name: 'app_dress_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dress = new Dress();
        $form = $this->createForm(DressType::class, $dress);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile) {
                $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                $photoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads',
                    $newFilename
                );
                $dress->setPhoto('/uploads/' . $newFilename);
            }

            $entityManager->persist($dress);
            $entityManager->flush();

            $this->addFlash('success', '服装登记成功！');
            return $this->redirectToRoute('app_dress_show', ['id' => $dress->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dress/new.html.twig', [
            'dress' => $dress,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dress_show', methods: ['GET'])]
    public function show(Dress $dress): Response
    {
        return $this->render('dress/show.html.twig', [
            'dress' => $dress,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dress_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Dress $dress, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DressType::class, $dress);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile) {
                $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                $photoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads',
                    $newFilename
                );
                $dress->setPhoto('/uploads/' . $newFilename);
            }

            $entityManager->flush();

            $this->addFlash('success', '服装信息已更新！');
            return $this->redirectToRoute('app_dress_show', ['id' => $dress->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dress/edit.html.twig', [
            'dress' => $dress,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dress_delete', methods: ['POST'])]
    public function delete(Request $request, Dress $dress, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $dress->getId(), $request->request->get('_token'))) {
            if ($dress->getStatus() === Dress::STATUS_RENTED) {
                $this->addFlash('error', '服装已租出，无法删除！');
                return $this->redirectToRoute('app_dress_show', ['id' => $dress->getId()]);
            }
            $entityManager->remove($dress);
            $entityManager->flush();
            $this->addFlash('success', '服装已删除');
        }

        return $this->redirectToRoute('app_dress_index', [], Response::HTTP_SEE_OTHER);
    }
}
