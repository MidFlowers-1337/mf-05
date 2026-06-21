<?php

namespace App\Service;

use App\Entity\DamageRecord;
use App\Entity\Dress;
use App\Entity\Rental;
use Doctrine\ORM\EntityManagerInterface;

class ReturnService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CleaningService $cleaningService,
    ) {
    }

    public function processReturn(
        Rental $rental,
        ?\DateTimeInterface $returnDate = null,
        array $damageItems = [],
        bool $needsCleaning = true
    ): Rental {
        if ($rental->getStatus() === Rental::STATUS_CLOSED) {
            throw new \InvalidArgumentException('该租单已结单，无法重复操作');
        }

        $returnDate = $returnDate ?? new \DateTime();

        if ($returnDate < $rental->getRentalDate()) {
            throw new \InvalidArgumentException('归还日期不能早于出租日期');
        }

        $rental->setReturnDate($returnDate);

        $totalDeduction = 0;
        foreach ($damageItems as $item) {
            $description = $item['description'] ?? '';
            $amount = (float)($item['amount'] ?? 0);

            if (empty($description) || $amount <= 0) {
                continue;
            }

            $damageRecord = new DamageRecord();
            $damageRecord->setDescription($description);
            $damageRecord->setDeductionAmount(number_format($amount, 2, '.', ''));
            $rental->addDamageRecord($damageRecord);
            $this->em->persist($damageRecord);

            $totalDeduction += $amount;
        }

        $depositPaid = (float)$rental->getDepositPaid();
        if ($totalDeduction > $depositPaid) {
            throw new \InvalidArgumentException(
                sprintf('损坏扣款总额 %.2f 超过押金 %.2f', $totalDeduction, $depositPaid)
            );
        }

        $rental->setDamageDeduction(number_format($totalDeduction, 2, '.', ''));
        $refundAmount = $depositPaid - $totalDeduction;
        $rental->setDepositRefunded(number_format($refundAmount, 2, '.', ''));

        $hasDamage = $totalDeduction > 0;
        if ($hasDamage) {
            $rental->getDress()->setStatus(Dress::STATUS_DAMAGED);
        }

        if ($needsCleaning && !$hasDamage) {
            $rental->getDress()->setStatus(Dress::STATUS_CLEANING);
            $this->cleaningService->scheduleCleaning($rental->getDress(), $rental);
        }

        if (!$needsCleaning && !$hasDamage) {
            $rental->getDress()->setStatus(Dress::STATUS_AVAILABLE);
        }

        $rental->setStatus(Rental::STATUS_RETURNED);

        $this->em->persist($rental);
        $this->em->persist($rental->getDress());
        $this->em->flush();

        return $rental;
    }

    public function closeRental(Rental $rental): Rental
    {
        if ($rental->getStatus() !== Rental::STATUS_RETURNED) {
            throw new \InvalidArgumentException('只有已归还状态的租单才能结单');
        }

        if ($rental->getDress()->getStatus() !== Dress::STATUS_AVAILABLE) {
            throw new \InvalidArgumentException('服装状态不是可出租，无法结单，请先处理损坏或清洗');
        }

        $rental->setStatus(Rental::STATUS_CLOSED);
        $this->em->persist($rental);
        $this->em->flush();

        return $rental;
    }

    public function calculateDamageTotal(Rental $rental): float
    {
        $total = 0;
        foreach ($rental->getDamageRecords() as $record) {
            $total += (float)$record->getDeductionAmount();
        }
        return $total;
    }

    public function calculateRefund(Rental $rental): float
    {
        $depositPaid = (float)$rental->getDepositPaid();
        $damageTotal = $this->calculateDamageTotal($rental);
        return max(0, $depositPaid - $damageTotal);
    }
}
