<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Repository\BankAccountRepository;
use App\Service\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Compte bancaire ou caisse (M09). */
#[ORM\Entity(repositoryClass: BankAccountRepository::class)]
#[ORM\Table(name: 'bank_accounts')]
#[ORM\UniqueConstraint(name: 'uniq_bank_accounts_name', columns: ['name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Ce libellé de compte existe déjà.')]
class BankAccount
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Assert\NotBlank(message: 'Le libellé du compte est obligatoire.')]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    private ?string $bankName = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $accountNumber = null;

    #[ORM\Column(type: Types::STRING, length: 34, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(type: Types::STRING, length: 3)]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Code devise attendu sur trois lettres majuscules.')]
    private string $currencyCode = 'TND';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $openingBalance = '0.000';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $openingDate;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, CashMovement> */
    #[ORM\OneToMany(targetEntity: CashMovement::class, mappedBy: 'bankAccount', cascade: ['persist'])]
    #[ORM\OrderBy(['movementDate' => 'DESC', 'createdAt' => 'DESC'])]
    private Collection $movements;

    public function __construct()
    {
        $this->ensureId();
        $this->movements = new ArrayCollection();
        $this->openingDate = new \DateTimeImmutable('today');
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

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(?string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;

        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): static
    {
        $this->currencyCode = strtoupper(trim($currencyCode));

        return $this;
    }

    public function getOpeningBalance(): string
    {
        return $this->openingBalance;
    }

    public function setOpeningBalance(string $openingBalance): static
    {
        $this->openingBalance = Money::normalize($openingBalance);

        return $this;
    }

    public function getOpeningDate(): \DateTimeImmutable
    {
        return $this->openingDate;
    }

    public function setOpeningDate(\DateTimeImmutable $openingDate): static
    {
        $this->openingDate = $openingDate;

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

    /** @return Collection<int, CashMovement> */
    public function getMovements(): Collection
    {
        return $this->movements;
    }

    public function addMovement(CashMovement $movement): static
    {
        if (!$this->movements->contains($movement)) {
            $this->movements->add($movement);
            $movement->setBankAccount($this);
        }

        return $this;
    }

    public function getBalance(): string
    {
        $balance = $this->openingBalance;
        foreach ($this->movements as $movement) {
            $balance = \App\Enum\TreasuryDirection::Inflow === $movement->getDirection()
                ? Money::add($balance, $movement->getAmount())
                : Money::sub($balance, $movement->getAmount());
        }

        return $balance;
    }
}
