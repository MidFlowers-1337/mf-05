<?php

namespace App\Entity;

use App\Repository\RentalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RentalRepository::class)]
class Rental
{
    public const STATUS_RENTED = 'rented';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CLOSED = 'closed';

    public const DEPOSIT_METHOD_CASH = 'cash';
    public const DEPOSIT_METHOD_ACCOUNT = 'account';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rentals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dress $dress = null;

    #[ORM\ManyToOne(inversedBy: 'rentals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $rentalDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $returnDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $depositPaid = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $depositRefunded = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $damageDeduction = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $rentalFee = null;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_RENTED;

    #[ORM\Column(length: 20, options: ['default' => 'cash'])]
    private ?string $depositMethod = self::DEPOSIT_METHOD_CASH;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'rental', targetEntity: DamageRecord::class, cascade: ['persist'])]
    private Collection $damageRecords;

    public function __construct()
    {
        $this->damageRecords = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDress(): ?Dress
    {
        return $this->dress;
    }

    public function setDress(?Dress $dress): self
    {
        $this->dress = $dress;
        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function getRentalDate(): ?\DateTimeInterface
    {
        return $this->rentalDate;
    }

    public function setRentalDate(\DateTimeInterface $rentalDate): self
    {
        $this->rentalDate = $rentalDate;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getReturnDate(): ?\DateTimeInterface
    {
        return $this->returnDate;
    }

    public function setReturnDate(?\DateTimeInterface $returnDate): self
    {
        $this->returnDate = $returnDate;
        return $this;
    }

    public function getDepositPaid(): ?string
    {
        return $this->depositPaid;
    }

    public function setDepositPaid(string $depositPaid): self
    {
        $this->depositPaid = $depositPaid;
        return $this;
    }

    public function getDepositRefunded(): ?string
    {
        return $this->depositRefunded;
    }

    public function setDepositRefunded(?string $depositRefunded): self
    {
        $this->depositRefunded = $depositRefunded;
        return $this;
    }

    public function getDamageDeduction(): ?string
    {
        return $this->damageDeduction;
    }

    public function setDamageDeduction(?string $damageDeduction): self
    {
        $this->damageDeduction = $damageDeduction;
        return $this;
    }

    public function getRentalFee(): ?string
    {
        return $this->rentalFee;
    }

    public function setRentalFee(?string $rentalFee): self
    {
        $this->rentalFee = $rentalFee;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDepositMethod(): ?string
    {
        return $this->depositMethod;
    }

    public function setDepositMethod(string $depositMethod): self
    {
        $this->depositMethod = $depositMethod;
        return $this;
    }

    public function getDepositMethodLabel(): string
    {
        $labels = [
            self::DEPOSIT_METHOD_CASH => '现金',
            self::DEPOSIT_METHOD_ACCOUNT => '账户余额',
        ];
        return $labels[$this->depositMethod] ?? $this->depositMethod;
    }

    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_RENTED => '已租出',
            self::STATUS_RETURNED => '已归还',
            self::STATUS_OVERDUE => '已逾期',
            self::STATUS_CLOSED => '已结单',
        ];
        return $labels[$this->status] ?? $this->status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, DamageRecord>
     */
    public function getDamageRecords(): Collection
    {
        return $this->damageRecords;
    }

    public function addDamageRecord(DamageRecord $damageRecord): self
    {
        if (!$this->damageRecords->contains($damageRecord)) {
            $this->damageRecords->add($damageRecord);
            $damageRecord->setRental($this);
        }
        return $this;
    }

    public function removeDamageRecord(DamageRecord $damageRecord): self
    {
        if ($this->damageRecords->removeElement($damageRecord)) {
            if ($damageRecord->getRental() === $this) {
                $damageRecord->setRental(null);
            }
        }
        return $this;
    }

    public function getRentalDays(): int
    {
        $end = $this->returnDate ?? new \DateTime();
        $diff = $end->diff($this->rentalDate);
        return max(1, (int)$diff->format('%a') + 1);
    }

    public function getOverdueDays(): int
    {
        $today = new \DateTime();
        if ($today <= $this->dueDate) {
            return 0;
        }
        $diff = $today->diff($this->dueDate);
        return (int)$diff->format('%a');
    }

    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_RETURNED || $this->status === self::STATUS_CLOSED) {
            return false;
        }
        $today = new \DateTime();
        return $today > $this->dueDate;
    }
}
