<?php

namespace App\Service;

use App\Entity\AccountTransaction;
use App\Entity\Customer;
use App\Entity\Rental;
use Doctrine\ORM\EntityManagerInterface;

class AccountService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function recharge(Customer $customer, string $amount, ?string $description = null): AccountTransaction
    {
        $amount = number_format((float)$amount, 2, '.', '');

        if ((float)$amount <= 0) {
            throw new \InvalidArgumentException('充值金额必须大于 0');
        }

        $currentBalance = (float)$customer->getBalance();
        $newBalance = number_format($currentBalance + (float)$amount, 2, '.', '');
        $customer->setBalance($newBalance);

        $transaction = new AccountTransaction();
        $transaction->setCustomer($customer);
        $transaction->setType(AccountTransaction::TYPE_RECHARGE);
        $transaction->setAmount($amount);
        $transaction->setBalanceAfter($newBalance);
        $transaction->setDescription($description ?? '账户充值');

        $this->em->persist($customer);
        $this->em->persist($transaction);
        $this->em->flush();

        return $transaction;
    }

    public function freezeDeposit(Rental $rental): AccountTransaction
    {
        $customer = $rental->getCustomer();
        $depositAmount = $rental->getDepositPaid();

        if ((float)$customer->getBalance() < (float)$depositAmount) {
            throw new \InvalidArgumentException(
                sprintf('账户余额 ¥%s 不足，无法支付押金 ¥%s，请先充值', $customer->getBalance(), $depositAmount)
            );
        }

        $currentBalance = (float)$customer->getBalance();
        $newBalance = number_format($currentBalance - (float)$depositAmount, 2, '.', '');
        $customer->setBalance($newBalance);

        $transaction = new AccountTransaction();
        $transaction->setCustomer($customer);
        $transaction->setType(AccountTransaction::TYPE_FREEZE);
        $transaction->setAmount($depositAmount);
        $transaction->setBalanceAfter($newBalance);
        $transaction->setDescription(sprintf('租单 #%d 冻结押金', $rental->getId()));
        $transaction->setRental($rental);

        $rental->setDepositMethod(Rental::DEPOSIT_METHOD_ACCOUNT);

        $this->em->persist($customer);
        $this->em->persist($transaction);
        $this->em->persist($rental);
        $this->em->flush();

        return $transaction;
    }

    public function processReturnRefund(Rental $rental, string $damageDeduction): array
    {
        if ($rental->getDepositMethod() !== Rental::DEPOSIT_METHOD_ACCOUNT) {
            throw new \InvalidArgumentException('该租单不是账户押金支付方式，无法从账户处理退款');
        }

        $customer = $rental->getCustomer();
        $depositPaid = (float)$rental->getDepositPaid();
        $deduction = (float)$damageDeduction;

        if ($deduction < 0) {
            throw new \InvalidArgumentException('损坏扣款不能为负数');
        }

        if ($deduction > $depositPaid) {
            throw new \InvalidArgumentException(
                sprintf('损坏扣款 ¥%.2f 超过押金 ¥%.2f', $deduction, $depositPaid)
            );
        }

        $transactions = [];
        $currentBalance = (float)$customer->getBalance();

        $unfreezeAmount = number_format($depositPaid, 2, '.', '');
        $balanceAfterUnfreeze = number_format($currentBalance + $depositPaid, 2, '.', '');
        $customer->setBalance($balanceAfterUnfreeze);

        $unfreezeTx = new AccountTransaction();
        $unfreezeTx->setCustomer($customer);
        $unfreezeTx->setType(AccountTransaction::TYPE_UNFREEZE);
        $unfreezeTx->setAmount($unfreezeAmount);
        $unfreezeTx->setBalanceAfter($balanceAfterUnfreeze);
        $unfreezeTx->setDescription(sprintf('租单 #%d 退还押金', $rental->getId()));
        $unfreezeTx->setRental($rental);
        $transactions[] = $unfreezeTx;

        $this->em->persist($unfreezeTx);

        if ($deduction > 0) {
            $deductionStr = number_format($deduction, 2, '.', '');
            $balanceAfterDeduction = number_format((float)$balanceAfterUnfreeze - $deduction, 2, '.', '');
            $customer->setBalance($balanceAfterDeduction);

            $deductTx = new AccountTransaction();
            $deductTx->setCustomer($customer);
            $deductTx->setType(AccountTransaction::TYPE_DAMAGE_DEDUCTION);
            $deductTx->setAmount($deductionStr);
            $deductTx->setBalanceAfter($balanceAfterDeduction);
            $deductTx->setDescription(sprintf('租单 #%d 损坏扣款', $rental->getId()));
            $deductTx->setRental($rental);
            $transactions[] = $deductTx;

            $this->em->persist($deductTx);
        }

        $this->em->persist($customer);
        $this->em->flush();

        return $transactions;
    }

    public function getBalance(Customer $customer): string
    {
        return $customer->getBalance() ?? '0.00';
    }

    public function canAfford(Customer $customer, string $amount): bool
    {
        return (float)$customer->getBalance() >= (float)$amount;
    }

    public function getTransactions(Customer $customer): array
    {
        return $this->em->getRepository(AccountTransaction::class)->findByCustomer($customer);
    }

    public function getMonthlyRechargeTotal(int $year, int $month): string
    {
        return $this->em->getRepository(AccountTransaction::class)->getMonthlyRechargeTotal($year, $month);
    }
}
