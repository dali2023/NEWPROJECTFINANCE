<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\PartnerType;
use App\Repository\PartnerRepository;
use App\Service\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Fiche associé (M10). */
#[ORM\Entity(repositoryClass: PartnerRepository::class)]
#[ORM\Table(name: 'partners')]
#[ORM\UniqueConstraint(name: 'uniq_partners_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Ce code associé existe déjà.')]
class Partner
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 30)]
    #[Assert\NotBlank(message: 'Le code est obligatoire.')]
    private string $code = '';

    #[ORM\Column(type: Types::STRING, length: 180)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: PartnerType::class)]
    private PartnerType $partnerType = PartnerType::Individual;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 4)]
    #[Assert\PositiveOrZero(message: 'La part ne peut pas être négative.')]
    private string $ownershipPercent = '0.0000';

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Email(message: 'Adresse électronique invalide.')]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, PartnerTransaction> */
    #[ORM\OneToMany(targetEntity: PartnerTransaction::class, mappedBy: 'partner', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['transactionDate' => 'DESC'])]
    private Collection $transactions;

    public function __construct()
    {
        $this->ensureId();
        $this->transactions = new ArrayCollection();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getPartnerType(): PartnerType
    {
        return $this->partnerType;
    }

    public function setPartnerType(PartnerType $partnerType): static
    {
        $this->partnerType = $partnerType;

        return $this;
    }

    public function getOwnershipPercent(): string
    {
        return $this->ownershipPercent;
    }

    public function setOwnershipPercent(string $ownershipPercent): static
    {
        $this->ownershipPercent = Money::normalize($ownershipPercent, Money::RATE_SCALE);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /** @return Collection<int, PartnerTransaction> */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(PartnerTransaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setPartner($this);
        }

        return $this;
    }

    public function getCapitalContributed(): string
    {
        return Money::sum(array_map(
            static fn (PartnerTransaction $transaction): string => \App\Enum\PartnerTransactionType::CapitalContribution === $transaction->getTransactionType()
                ? $transaction->getAmount()
                : Money::zero(),
            $this->transactions->toArray(),
        ));
    }

    public function getCurrentAccountBalance(): string
    {
        $balance = Money::zero();
        foreach ($this->transactions as $transaction) {
            $balance = match ($transaction->getTransactionType()) {
                \App\Enum\PartnerTransactionType::CurrentAccountContribution => Money::add($balance, $transaction->getAmount()),
                \App\Enum\PartnerTransactionType::CurrentAccountRepayment => Money::sub($balance, $transaction->getAmount()),
                default => $balance,
            };
        }

        return $balance;
    }

    public function getDividendsReceived(): string
    {
        return Money::sum(array_map(
            static fn (PartnerTransaction $transaction): string => \App\Enum\PartnerTransactionType::Dividend === $transaction->getTransactionType()
                ? $transaction->getAmount()
                : Money::zero(),
            $this->transactions->toArray(),
        ));
    }
}
