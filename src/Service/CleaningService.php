<?php

namespace App\Service;

use App\Entity\CleaningRecord;
use App\Entity\Dress;
use App\Entity\Rental;
use Doctrine\ORM\EntityManagerInterface;

class CleaningService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function scheduleCleaning(Dress $dress, ?Rental $rental = null, ?\DateTimeImmutable $scheduledAt = null): CleaningRecord
    {
        $record = new CleaningRecord();
        $record->setDress($dress);
        $record->setRental($rental);
        $record->setStatus(CleaningRecord::STATUS_PENDING);

        if ($scheduledAt) {
            $record->setScheduledAt($scheduledAt);
        }

        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    public function startCleaning(CleaningRecord $record): CleaningRecord
    {
        if ($record->getStatus() !== CleaningRecord::STATUS_PENDING) {
            throw new \InvalidArgumentException('该清洗记录不是待清洗状态');
        }

        $record->setStatus(CleaningRecord::STATUS_CLEANING);
        $record->setStartedAt(new \DateTimeImmutable());

        $dress = $record->getDress();
        if ($dress->getStatus() !== Dress::STATUS_CLEANING) {
            $dress->setStatus(Dress::STATUS_CLEANING);
            $this->em->persist($dress);
        }

        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    public function completeCleaning(CleaningRecord $record): CleaningRecord
    {
        if ($record->getStatus() !== CleaningRecord::STATUS_CLEANING) {
            throw new \InvalidArgumentException('该清洗记录不是清洗中状态');
        }

        $record->setStatus(CleaningRecord::STATUS_DONE);
        $record->setCompletedAt(new \DateTimeImmutable());

        $dress = $record->getDress();
        if ($dress->getStatus() === Dress::STATUS_CLEANING) {
            $dress->setStatus(Dress::STATUS_AVAILABLE);
            $this->em->persist($dress);
        }

        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    public function markDamagedDressReady(Dress $dress): void
    {
        if ($dress->getStatus() !== Dress::STATUS_DAMAGED) {
            throw new \InvalidArgumentException('该服装不是损坏状态');
        }

        $dress->setStatus(Dress::STATUS_CLEANING);
        $this->em->persist($dress);
        $this->scheduleCleaning($dress);
    }

    public function getPendingList(): array
    {
        return $this->em->getRepository(CleaningRecord::class)->findPending();
    }

    public function getInProgressList(): array
    {
        return $this->em->getRepository(CleaningRecord::class)->findInProgress();
    }
}
