<?php

namespace App\Tests\Service;

use App\Entity\AccountTransaction;
use App\Entity\Customer;
use App\Entity\Dress;
use App\Entity\Rental;
use App\Service\AccountService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AccountServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private AccountService $accountService;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->accountService = new AccountService($this->em);

        $this->customer = new Customer();
        $this->customer->setName('测试客户');
        $this->customer->setPhone('13800138000');
        $this->customer->setBalance('0.00');
    }

    public function testRechargeIncreasesBalance(): void
    {
        $this->customer->setBalance('100.00');

        $this->em->expects($this->exactly(2))
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->accountService->recharge($this->customer, '500.00', '测试充值');

        $this->assertEquals('600.00', $this->customer->getBalance());
        $this->assertEquals(AccountTransaction::TYPE_RECHARGE, $result->getType());
        $this->assertEquals('500.00', $result->getAmount());
        $this->assertEquals('600.00', $result->getBalanceAfter());
        $this->assertEquals('测试充值', $result->getDescription());
        $this->assertSame($this->customer, $result->getCustomer());
    }

    public function testRechargeWithZeroAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('充值金额必须大于 0');

        $this->accountService->recharge($this->customer, '0.00');
    }

    public function testRechargeWithNegativeAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('充值金额必须大于 0');

        $this->accountService->recharge($this->customer, '-100.00');
    }

    public function testFreezeDepositDecreasesBalance(): void
    {
        $this->customer->setBalance('3000.00');

        $dress = new Dress();
        $dress->setDeposit('2000.00');

        $rental = new Rental();
        $rental->setDress($dress);
        $rental->setCustomer($this->customer);
        $rental->setDepositPaid('2000.00');

        $this->em->expects($this->exactly(3))
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->accountService->freezeDeposit($rental);

        $this->assertEquals('1000.00', $this->customer->getBalance());
        $this->assertEquals(AccountTransaction::TYPE_FREEZE, $result->getType());
        $this->assertEquals('2000.00', $result->getAmount());
        $this->assertEquals('1000.00', $result->getBalanceAfter());
        $this->assertEquals(Rental::DEPOSIT_METHOD_ACCOUNT, $rental->getDepositMethod());
        $this->assertSame($rental, $result->getRental());
    }

    public function testFreezeDepositInsufficientBalanceThrowsException(): void
    {
        $this->customer->setBalance('1000.00');

        $dress = new Dress();
        $dress->setDeposit('2000.00');

        $rental = new Rental();
        $rental->setDress($dress);
        $rental->setCustomer($this->customer);
        $rental->setDepositPaid('2000.00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('账户余额');

        $this->accountService->freezeDeposit($rental);
    }

    public function testProcessReturnRefundNoDamage(): void
    {
        $this->customer->setBalance('1000.00');

        $rental = new Rental();
        $rental->setCustomer($this->customer);
        $rental->setDepositPaid('2000.00');
        $rental->setDepositMethod(Rental::DEPOSIT_METHOD_ACCOUNT);

        $this->em->expects($this->exactly(2))
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $transactions = $this->accountService->processReturnRefund($rental, '0.00');

        $this->assertCount(1, $transactions);
        $this->assertEquals(AccountTransaction::TYPE_UNFREEZE, $transactions[0]->getType());
        $this->assertEquals('2000.00', $transactions[0]->getAmount());
        $this->assertEquals('3000.00', $this->customer->getBalance());
        $this->assertEquals('3000.00', $transactions[0]->getBalanceAfter());
    }

    public function testProcessReturnRefundWithDamage(): void
    {
        $this->customer->setBalance('1000.00');

        $rental = new Rental();
        $rental->setCustomer($this->customer);
        $rental->setDepositPaid('2000.00');
        $rental->setDepositMethod(Rental::DEPOSIT_METHOD_ACCOUNT);

        $this->em->expects($this->exactly(3))
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $transactions = $this->accountService->processReturnRefund($rental, '500.00');

        $this->assertCount(2, $transactions);

        $unfreezeTx = $transactions[0];
        $this->assertEquals(AccountTransaction::TYPE_UNFREEZE, $unfreezeTx->getType());
        $this->assertEquals('2000.00', $unfreezeTx->getAmount());
        $this->assertEquals('3000.00', $unfreezeTx->getBalanceAfter());

        $deductTx = $transactions[1];
        $this->assertEquals(AccountTransaction::TYPE_DAMAGE_DEDUCTION, $deductTx->getType());
        $this->assertEquals('500.00', $deductTx->getAmount());
        $this->assertEquals('2500.00', $deductTx->getBalanceAfter());

        $this->assertEquals('2500.00', $this->customer->getBalance());
    }

    public function testProcessReturnRefundDamageExceedsDepositThrowsException(): void
    {
        $rental = new Rental();
        $rental->setCustomer($this->customer);
        $rental->setDepositPaid('2000.00');
        $rental->setDepositMethod(Rental::DEPOSIT_METHOD_ACCOUNT);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('损坏扣款');

        $this->accountService->processReturnRefund($rental, '2500.00');
    }

    public function testProcessReturnRefundNegativeDamageThrowsException(): void
    {
        $rental = new Rental();
        $rental->setCustomer($this->customer);
        $rental->setDepositPaid('2000.00');
        $rental->setDepositMethod(Rental::DEPOSIT_METHOD_ACCOUNT);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('损坏扣款不能为负数');

        $this->accountService->processReturnRefund($rental, '-100.00');
    }

    public function testProcessReturnRefundCashMethodThrowsException(): void
    {
        $rental = new Rental();
        $rental->setCustomer($this->customer);
        $rental->setDepositPaid('2000.00');
        $rental->setDepositMethod(Rental::DEPOSIT_METHOD_CASH);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不是账户押金支付方式');

        $this->accountService->processReturnRefund($rental, '0.00');
    }

    public function testCanAfford(): void
    {
        $this->customer->setBalance('2000.00');

        $this->assertTrue($this->accountService->canAfford($this->customer, '2000.00'));
        $this->assertTrue($this->accountService->canAfford($this->customer, '1500.00'));
        $this->assertFalse($this->accountService->canAfford($this->customer, '2000.01'));
        $this->assertFalse($this->accountService->canAfford($this->customer, '3000.00'));
    }

    public function testGetBalance(): void
    {
        $this->customer->setBalance('1234.56');
        $this->assertEquals('1234.56', $this->accountService->getBalance($this->customer));
    }

    public function testRechargeAmountFormattedProperly(): void
    {
        $this->customer->setBalance('100.00');

        $this->em->expects($this->exactly(2))
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->accountService->recharge($this->customer, '500');

        $this->assertEquals('600.00', $this->customer->getBalance());
        $this->assertEquals('500.00', $result->getAmount());
    }

    public function testDecimalPrecisionPreserved(): void
    {
        $this->customer->setBalance('0.10');

        $this->em->expects($this->exactly(2))
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $this->accountService->recharge($this->customer, '0.20');

        $this->assertEquals('0.30', $this->customer->getBalance());
    }
}
