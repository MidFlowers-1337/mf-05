<?php

namespace App\Service;

use App\Entity\AccountTransaction;
use App\Entity\Dress;
use App\Entity\Rental;
use Doctrine\ORM\EntityManagerInterface;

class StatsService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function getMonthlyStats(?int $year = null, ?int $month = null): array
    {
        if ($year === null) {
            $year = (int)date('Y');
        }
        if ($month === null) {
            $month = (int)date('n');
        }

        $from = new \DateTime(sprintf('%d-%02d-01', $year, $month));
        $to = new \DateTime(sprintf('%d-%02d-%d', $year, $month, (int)$from->format('t')));

        $rentalRepo = $this->em->getRepository(Rental::class);
        $dressRepo = $this->em->getRepository(Dress::class);
        $txRepo = $this->em->getRepository(AccountTransaction::class);

        $rentals = $rentalRepo->findByDateRange($from, $to);

        $totalRentals = count($rentals);
        $totalRevenue = 0;
        $totalDamageDeductions = 0;

        foreach ($rentals as $rental) {
            $totalRevenue += (float)$rental->getRentalFee();
            $totalDamageDeductions += (float)$rental->getDamageDeduction();
        }

        $totalDresses = $dressRepo->count([]);

        $daysInMonth = (int)$from->format('t');
        $maxPossibleRentalDays = $totalDresses * $daysInMonth;

        $actualRentalDays = 0;
        foreach ($rentals as $rental) {
            $rentalStart = max($rental->getRentalDate(), $from);
            $rentalEnd = $rental->getReturnDate() ? min($rental->getReturnDate(), $to) : $to;
            $diff = $rentalEnd->diff($rentalStart);
            $actualRentalDays += max(0, (int)$diff->format('%a') + 1);
        }

        $rentalRate = $maxPossibleRentalDays > 0
            ? round(($actualRentalDays / $maxPossibleRentalDays) * 100, 2)
            : 0;

        $mostRented = $dressRepo->findMostRented(10, $from, $to);

        $totalRecharges = $txRepo->getMonthlyRechargeTotal($year, $month);

        return [
            'year' => $year,
            'month' => $month,
            'totalRentals' => $totalRentals,
            'totalRevenue' => number_format($totalRevenue, 2, '.', ''),
            'totalDamageDeductions' => number_format($totalDamageDeductions, 2, '.', ''),
            'totalRecharges' => $totalRecharges,
            'totalDresses' => $totalDresses,
            'rentalRate' => $rentalRate,
            'actualRentalDays' => $actualRentalDays,
            'maxPossibleRentalDays' => $maxPossibleRentalDays,
            'mostRented' => $mostRented,
        ];
    }

    public function getOverview(): array
    {
        $rentalRepo = $this->em->getRepository(Rental::class);
        $dressRepo = $this->em->getRepository(Dress::class);

        $totalDresses = $dressRepo->count([]);
        $availableDresses = $dressRepo->count(['status' => Dress::STATUS_AVAILABLE]);
        $rentedDresses = $dressRepo->count(['status' => Dress::STATUS_RENTED]);
        $cleaningDresses = $dressRepo->count(['status' => Dress::STATUS_CLEANING]);
        $damagedDresses = $dressRepo->count(['status' => Dress::STATUS_DAMAGED]);

        $overdueRentals = $rentalRepo->findOverdue();
        $activeRentals = $rentalRepo->findActive();

        return [
            'totalDresses' => $totalDresses,
            'availableDresses' => $availableDresses,
            'rentedDresses' => $rentedDresses,
            'cleaningDresses' => $cleaningDresses,
            'damagedDresses' => $damagedDresses,
            'activeRentals' => count($activeRentals),
            'overdueRentals' => count($overdueRentals),
        ];
    }
}
