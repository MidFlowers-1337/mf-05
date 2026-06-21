<?php

namespace App\Repository;

use App\Entity\DamageRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DamageRecord>
 *
 * @method DamageRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method DamageRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method DamageRecord[]    findAll()
 * @method DamageRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DamageRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DamageRecord::class);
    }
}
