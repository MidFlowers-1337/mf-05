<?php

namespace App\Repository;

use App\Entity\AccountTransaction;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountTransaction>
 *
 * @method AccountTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method AccountTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method AccountTransaction[]    findAll()
 * @method AccountTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccountTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountTransaction::class);
    }

    public function findByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getMonthlyRechargeTotal(int $year, int $month): string
    {
        $from = new \DateTime(sprintf('%d-%02d-01', $year, $month));
        $to = new \DateTime(sprintf('%d-%02d-%d 23:59:59', $year, $month, (int)$from->format('t')));

        $result = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->andWhere('t.type = :type')
            ->andWhere('t.createdAt >= :from')
            ->andWhere('t.createdAt <= :to')
            ->setParameter('type', AccountTransaction::TYPE_RECHARGE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format((float)$result, 2, '.', '');
    }
}
