<?php

namespace App\Repository;

use App\Entity\CleaningRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CleaningRecord>
 *
 * @method CleaningRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method CleaningRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method CleaningRecord[]    findAll()
 * @method CleaningRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CleaningRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CleaningRecord::class);
    }

    public function findPending(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', CleaningRecord::STATUS_PENDING)
            ->orderBy('c.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findInProgress(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', CleaningRecord::STATUS_CLEANING)
            ->orderBy('c.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
