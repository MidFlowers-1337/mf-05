<?php

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Entity\DamageRecord;
use App\Entity\Dress;
use App\Entity\Rental;
use App\Service\AccountService;
use App\Service\CleaningService;
use App\Service\ReturnService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ReturnServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private CleaningService $cleaningService;
    private AccountService $accountService;
    private ReturnService $returnService;
    private Dress $dress;
    private Customer $customer;
    private Rental $rental;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->cleaningService = $this->createMock(CleaningService::class);
        $this->accountService = $this->createMock(AccountService::class);
        $this->returnService = new ReturnService($this->em, $this->cleaningService, $this->accountService);

        $this->dress = new Dress();
        $this->dress->setName('测试婚纱');
        $this->dress->setSize('M');
        $this->dress->setColor('白色');
        $this->dress->setPurchasePrice('5000.00');
        $this->dress->setDeposit('2000.00');
        $this->dress->setDailyRate('300.00');
        $this->dress->setStatus(Dress::STATUS_RENTED);

        $this->customer = new Customer();
        $this->customer->setName('张三');
        $this->customer->setPhone('13800138000');

        $this->rental = new Rental();
        $this->rental->setDress($this->dress);
        $this->rental->setCustomer($this->customer);
        $this->rental->setRentalDate(new \DateTime('2026-06-01'));
        $this->rental->setDueDate(new \DateTime('2026-06-03'));
        $this->rental->setDepositPaid('2000.00');
        $this->rental->setRentalFee('900.00');
        $this->rental->setStatus(Rental::STATUS_RENTED);
    }

    public function testProcessReturnWithoutDamage(): void
    {
        $this->em->expects($this->atLeastOnce())
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $this->cleaningService->expects($this->once())
            ->method('scheduleCleaning');

        $returnDate = new \DateTime('2026-06-03');
        $result = $this->returnService->processReturn($this->rental, $returnDate, [], true);

        $this->assertEquals(Rental::STATUS_RETURNED, $result->getStatus());
        $this->assertEquals($returnDate, $result->getReturnDate());
        $this->assertEquals('0.00', $result->getDamageDeduction());
        $this->assertEquals('2000.00', $result->getDepositRefunded());
        $this->assertEquals(Dress::STATUS_CLEANING, $this->dress->getStatus());
    }

    public function testProcessReturnWithDamage(): void
    {
        $this->em->expects($this->atLeastOnce())
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $this->cleaningService->expects($this->never())
            ->method('scheduleCleaning');

        $damageItems = [
            ['description' => '裙摆破洞', 'amount' => 300],
            ['description' => '领口污渍', 'amount' => 200],
        ];

        $returnDate = new \DateTime('2026-06-03');
        $result = $this->returnService->processReturn($this->rental, $returnDate, $damageItems, true);

        $this->assertEquals(Rental::STATUS_RETURNED, $result->getStatus());
        $this->assertEquals('500.00', $result->getDamageDeduction());
        $this->assertEquals('1500.00', $result->getDepositRefunded());
        $this->assertEquals(Dress::STATUS_DAMAGED, $this->dress->getStatus());
        $this->assertCount(2, $result->getDamageRecords());
    }

    public function testProcessReturnDamageExceedsDepositThrowsException(): void
    {
        $damageItems = [
            ['description' => '严重损坏', 'amount' => 2500],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('损坏扣款总额 2500.00 超过押金 2000.00');

        $this->returnService->processReturn($this->rental, new \DateTime('2026-06-03'), $damageItems);
    }

    public function testProcessReturnClosedRentalThrowsException(): void
    {
        $this->rental->setStatus(Rental::STATUS_CLOSED);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('该租单已结单，无法重复操作');

        $this->returnService->processReturn($this->rental);
    }

    public function testProcessReturnInvalidReturnDateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('归还日期不能早于出租日期');

        $this->returnService->processReturn($this->rental, new \DateTime('2026-05-01'));
    }

    public function testProcessReturnNoCleaningNeeded(): void
    {
        $this->em->expects($this->atLeastOnce())
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');

        $this->cleaningService->expects($this->never())
            ->method('scheduleCleaning');

        $returnDate = new \DateTime('2026-06-03');
        $result = $this->returnService->processReturn($this->rental, $returnDate, [], false);

        $this->assertEquals('2000.00', $result->getDepositRefunded());
        $this->assertEquals(Dress::STATUS_AVAILABLE, $this->dress->getStatus());
    }

    public function testRentalFeeRecalculatedOnReturn(): void
    {
        $this->em->expects($this->atLeastOnce())
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');
        $this->cleaningService->expects($this->once())
            ->method('scheduleCleaning');

        $returnDate = new \DateTime('2026-06-05');
        $result = $this->returnService->processReturn($this->rental, $returnDate, [], true);

        $this->assertEquals('1500.00', $result->getRentalFee());
        $this->assertEquals(5, $result->getRentalDays());
    }

    public function testRentalFeeEarlyReturnRecalculated(): void
    {
        $this->em->expects($this->atLeastOnce())
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');
        $this->cleaningService->expects($this->once())
            ->method('scheduleCleaning');

        $returnDate = new \DateTime('2026-06-02');
        $result = $this->returnService->processReturn($this->rental, $returnDate, [], true);

        $this->assertEquals('600.00', $result->getRentalFee());
        $this->assertEquals(2, $result->getRentalDays());
    }

    public function testRentalFeeOnDueDateRecalculated(): void
    {
        $this->em->expects($this->atLeastOnce())
            ->method('persist');
        $this->em->expects($this->once())
            ->method('flush');
        $this->cleaningService->expects($this->once())
            ->method('scheduleCleaning');

        $returnDate = new \DateTime('2026-06-03');
        $result = $this->returnService->processReturn($this->rental, $returnDate, [], true);

        $this->assertEquals('900.00', $result->getRentalFee());
        $this->assertEquals(3, $result->getRentalDays());
    }

    public function testCalculateDamageTotal(): void
    {
        $damage1 = new DamageRecord();
        $damage1->setDescription('破洞');
        $damage1->setDeductionAmount('300.00');

        $damage2 = new DamageRecord();
        $damage2->setDescription('污渍');
        $damage2->setDeductionAmount('150.50');

        $this->rental->addDamageRecord($damage1);
        $this->rental->addDamageRecord($damage2);

        $total = $this->returnService->calculateDamageTotal($this->rental);
        $this->assertEquals(450.50, $total);
    }

    public function testCalculateRefund(): void
    {
        $damage1 = new DamageRecord();
        $damage1->setDescription('破洞');
        $damage1->setDeductionAmount('500.00');

        $this->rental->addDamageRecord($damage1);

        $refund = $this->returnService->calculateRefund($this->rental);
        $this->assertEquals(1500.00, $refund);
    }

    public function testCalculateRefundNoDamage(): void
    {
        $refund = $this->returnService->calculateRefund($this->rental);
        $this->assertEquals(2000.00, $refund);
    }

    public function testCloseRentalSuccess(): void
    {
        $this->rental->setStatus(Rental::STATUS_RETURNED);
        $this->dress->setStatus(Dress::STATUS_AVAILABLE);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->returnService->closeRental($this->rental);
        $this->assertEquals(Rental::STATUS_CLOSED, $result->getStatus());
    }

    public function testCloseRentalNotReturnedThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('只有已归还状态的租单才能结单');

        $this->returnService->closeRental($this->rental);
    }

    public function testCloseRentalDressNotAvailableThrowsException(): void
    {
        $this->rental->setStatus(Rental::STATUS_RETURNED);
        $this->dress->setStatus(Dress::STATUS_CLEANING);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('服装状态不是可出租，无法结单');

        $this->returnService->closeRental($this->rental);
    }

    public function testEmptyDamageItemsAreIgnored(): void
    {
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $damageItems = [
            ['description' => '', 'amount' => 0],
            ['description' => '有效损坏', 'amount' => 100],
            ['description' => '', 'amount' => 50],
            ['description' => '只有描述没金额', 'amount' => 0],
        ];

        $returnDate = new \DateTime('2026-06-03');
        $result = $this->returnService->processReturn($this->rental, $returnDate, $damageItems, false);

        $this->assertEquals('100.00', $result->getDamageDeduction());
        $this->assertCount(1, $result->getDamageRecords());
    }
}
