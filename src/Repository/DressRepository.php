<?php

namespace App\Repository;

use App\Entity\Dress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dress>
 *
 * @method Dress|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dress|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dress[]    findAll()
 * @method Dress[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dress::class);
    }

    public function findAvailable(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', Dress::STATUS_AVAILABLE)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findMostRented(int $limit = 10, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.rentals', 'r')
            ->select('d, COUNT(r.id) as rentalCount')
            ->groupBy('d.id')
            ->orderBy('rentalCount', 'DESC')
            ->setMaxResults($limit);

        if ($from) {
            $qb->andWhere('r.rentalDate >= :from')
               ->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('r.rentalDate <= :to')
               ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }
}
