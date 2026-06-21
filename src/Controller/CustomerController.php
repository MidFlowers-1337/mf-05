<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Service\AccountService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/customer')]
class CustomerController extends AbstractController
{
    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer, AccountService $accountService): Response
    {
        $transactions = $accountService->getTransactions($customer);

        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
            'transactions' => $transactions,
            'balance' => $accountService->getBalance($customer),
        ]);
    }

    #[Route('/{id}/recharge', name: 'app_customer_recharge', methods: ['POST'])]
    public function recharge(
        Request $request,
        Customer $customer,
        AccountService $accountService,
    ): Response {
        if (!$this->isCsrfTokenValid('recharge' . $customer->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', '无效的安全令牌');
            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }

        $amount = $request->request->get('amount', '0');
        $description = $request->request->get('description', '');

        try {
            $accountService->recharge($customer, $amount, $description ?: null);
            $this->addFlash('success', sprintf('充值成功！账户余额 ¥%s', $customer->getBalance()));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
    }
}
