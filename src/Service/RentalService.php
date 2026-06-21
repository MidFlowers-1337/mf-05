<?php

namespace App\Service;

use App\Entity\Dress;
use App\Entity\Customer;
use App\Entity\Rental;
use Doctrine\ORM\EntityManagerInterface;

class RentalService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function createRental(
        Dress $dress,
        Customer $customer,
        \DateTimeInterface $rentalDate,
        \DateTimeInterface $dueDate,
        ?string $notes = null
    ): Rental {
        if (!$dress->isAvailable()) {
            throw new \InvalidArgumentException('该服装当前不可出租，状态：' . $dress->getStatusLabel());
        }

        if ($dueDate < $rentalDate) {
            throw new \InvalidArgumentException('归还日期不能早于出租日期');
        }

        $rental = new Rental();
        $rental->setDress($dress);
        $rental->setCustomer($customer);
        $rental->setRentalDate($rentalDate);
        $rental->setDueDate($dueDate);
        $rental->setDepositPaid($dress->getDeposit());
        $rental->setStatus(Rental::STATUS_RENTED);
        $rental->setNotes($notes);

        $days = $this->calculateDays($rentalDate, $dueDate);
        $dailyRate = (float)$dress->getDailyRate();
        $rentalFee = number_format($days * $dailyRate, 2, '.', '');
        $rental->setRentalFee($rentalFee);

        $dress->setStatus(Dress::STATUS_RENTED);

        $this->em->persist($rental);
        $this->em->persist($dress);
        $this->em->flush();

        return $rental;
    }

    public function calculateDays(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        $diff = $end->diff($start);
        return max(1, (int)$diff->format('%a') + 1);
    }

    public function updateOverdueStatuses(): int
    {
        $today = new \DateTime();
        $repo = $this->em->getRepository(Rental::class);
        $activeRentals = $repo->findBy([
            'status' => [Rental::STATUS_RENTED],
        ]);

        $updated = 0;
        foreach ($activeRentals as $rental) {
            if ($rental->getDueDate() < $today) {
                $rental->setStatus(Rental::STATUS_OVERDUE);
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->em->flush();
        }

        return $updated;
    }

    public function findOrCreateCustomer(string $name, ?string $phone = null, ?string $idCard = null, ?string $address = null): Customer
    {
        $repo = $this->em->getRepository(Customer::class);

        if ($phone) {
            $existing = $repo->findOneBy(['phone' => $phone]);
            if ($existing) {
                return $existing;
            }
        }

        if ($idCard) {
            $existing = $repo->findOneBy(['idCard' => $idCard]);
            if ($existing) {
                return $existing;
            }
        }

        $customer = new Customer();
        $customer->setName($name);
        $customer->setPhone($phone);
        $customer->setIdCard($idCard);
        $customer->setAddress($address);

        $this->em->persist($customer);
        $this->em->flush();

        return $customer;
    }
}
