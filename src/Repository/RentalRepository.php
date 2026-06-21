<?php

namespace App\Repository;

use App\Entity\Rental;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rental>
 *
 * @method Rental|null find($id, $lockMode = null, $lockVersion = null)
 * @method Rental|null findOneBy(array $criteria, array $orderBy = null)
 * @method Rental[]    findAll()
 * @method Rental[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RentalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rental::class);
    }

    public function findOverdue(): array
    {
        $today = new \DateTime();
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:activeStatuses)')
            ->andWhere('r.dueDate < :today')
            ->setParameter('activeStatuses', [Rental::STATUS_RENTED, Rental::STATUS_OVERDUE])
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('r.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('activeStatuses', [Rental::STATUS_RENTED, Rental::STATUS_OVERDUE])
            ->orderBy('r.rentalDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.rentalDate >= :from')
            ->andWhere('r.rentalDate <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.rentalDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByMonth(\DateTimeInterface $month): int
    {
        $from = new \DateTime($month->format('Y-m-01'));
        $to = new \DateTime($month->format('Y-m-t'));
        return (int)$this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.rentalDate >= :from')
            ->andWhere('r.rentalDate <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
