<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use App\Repository\CashMovementRepository;
use App\Service\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Mouvement de trésorerie (M09). */
#[ORM\Entity(repositoryClass: CashMovementRepository::class)]
#[ORM\Table(name: 'cash_movements')]
#[ORM\UniqueConstraint(name: 'uniq_cash_movements_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_cash_movements_account', columns: ['bank_account_id'])]
#[ORM\Index(name: 'idx_cash_movements_date', columns: ['movement_date'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence de mouvement existe déjà.')]
class CashMovement
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $reference = '';

    #[ORM\ManyToOne(targetEntity: BankAccount::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(name: 'bank_account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?BankAccount $bankAccount = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $movementDate;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: TreasuryDirection::class)]
    private TreasuryDirection $direction = TreasuryDirection::Inflow;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: TreasuryMovementType::class)]
    private TreasuryMovementType $movementType = TreasuryMovementType::Other;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    #[Assert\Positive(message: 'Le montant doit être positif.')]
    private string $amount = '0.000';

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    private string $label = '';

    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    private ?string $sourceId = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $reconciled = false;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reconciledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reconciled_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reconciledBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->ensureId();
        $this->movementDate = new \DateTimeImmutable('today');
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = strtoupper(trim($reference));

        return $this;
    }

    public function getBankAccount(): ?BankAccount
    {
        return $this->bankAccount;
    }

    public function setBankAccount(?BankAccount $bankAccount): static
    {
        $this->bankAccount = $bankAccount;

        return $this;
    }

    public function getMovementDate(): \DateTimeImmutable
    {
        return $this->movementDate;
    }

    public function setMovementDate(\DateTimeImmutable $movementDate): static
    {
        $this->movementDate = $movementDate;

        return $this;
    }

    public function getDirection(): TreasuryDirection
    {
        return $this->direction;
    }

    public function setDirection(TreasuryDirection $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getMovementType(): TreasuryMovementType
    {
        return $this->movementType;
    }

    public function setMovementType(TreasuryMovementType $movementType): static
    {
        $this->movementType = $movementType;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = Money::normalize($amount);

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);

        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function setSourceId(?string $sourceId): static
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    public function isReconciled(): bool
    {
        return $this->reconciled;
    }

    public function getReconciledAt(): ?\DateTimeImmutable
    {
        return $this->reconciledAt;
    }

    public function getReconciledBy(): ?User
    {
        return $this->reconciledBy;
    }

    public function reconcile(User $user, ?\DateTimeImmutable $at = null): void
    {
        $this->reconciled = true;
        $this->reconciledAt = $at ?? new \DateTimeImmutable();
        $this->reconciledBy = $user;
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
}
