<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\PartnerTransactionStatus;
use App\Enum\PartnerTransactionType;
use App\Repository\PartnerTransactionRepository;
use App\Service\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Opération avec un associé : apport, compte courant, dividende (M10). */
#[ORM\Entity(repositoryClass: PartnerTransactionRepository::class)]
#[ORM\Table(name: 'partner_transactions')]
#[ORM\UniqueConstraint(name: 'uniq_partner_transactions_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_partner_transactions_partner', columns: ['partner_id'])]
#[ORM\Index(name: 'idx_partner_transactions_type', columns: ['transaction_type'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence d’opération existe déjà.')]
class PartnerTransaction
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $reference = '';

    #[ORM\ManyToOne(targetEntity: Partner::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'partner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Partner $partner = null;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: PartnerTransactionType::class)]
    private PartnerTransactionType $transactionType = PartnerTransactionType::Other;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: PartnerTransactionStatus::class)]
    private PartnerTransactionStatus $status = PartnerTransactionStatus::Planned;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $transactionDate;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    #[Assert\Positive(message: 'Le montant doit être positif.')]
    private string $amount = '0.000';

    #[ORM\Column(type: Types::STRING, length: 3)]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Code devise attendu sur trois lettres majuscules.')]
    private string $currencyCode = 'TND';

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    private string $label = '';

    #[ORM\ManyToOne(targetEntity: BankAccount::class)]
    #[ORM\JoinColumn(name: 'bank_account_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?BankAccount $bankAccount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'validated_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct()
    {
        $this->ensureId();
        $this->transactionDate = new \DateTimeImmutable('today');
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

    public function getPartner(): ?Partner
    {
        return $this->partner;
    }

    public function setPartner(?Partner $partner): static
    {
        $this->partner = $partner;

        return $this;
    }

    public function getTransactionType(): PartnerTransactionType
    {
        return $this->transactionType;
    }

    public function setTransactionType(PartnerTransactionType $transactionType): static
    {
        $this->transactionType = $transactionType;

        return $this;
    }

    public function getStatus(): PartnerTransactionStatus
    {
        return $this->status;
    }

    public function setStatus(PartnerTransactionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTransactionDate(): \DateTimeImmutable
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(\DateTimeImmutable $transactionDate): static
    {
        $this->transactionDate = $transactionDate;

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

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): static
    {
        $this->currencyCode = strtoupper(trim($currencyCode));

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

    public function getBankAccount(): ?BankAccount
    {
        return $this->bankAccount;
    }

    public function setBankAccount(?BankAccount $bankAccount): static
    {
        $this->bankAccount = $bankAccount;

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

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function validate(User $user, ?\DateTimeImmutable $at = null): void
    {
        if (PartnerTransactionStatus::Planned !== $this->status) {
            throw new \LogicException('Seule une opération prévue peut être validée.');
        }

        $this->status = PartnerTransactionStatus::Validated;
        $this->validatedBy = $user;
        $this->validatedAt = $at ?? new \DateTimeImmutable();
    }

    public function markPaid(?\DateTimeImmutable $at = null): void
    {
        if (!\in_array($this->status, [PartnerTransactionStatus::Validated, PartnerTransactionStatus::Planned], true)) {
            throw new \LogicException('Cette opération ne peut pas être réglée.');
        }

        $this->status = PartnerTransactionStatus::Paid;
        $this->paidAt = $at ?? new \DateTimeImmutable();
    }
}
