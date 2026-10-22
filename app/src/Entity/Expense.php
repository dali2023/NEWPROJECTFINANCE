<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ExpenseCategory;
use App\Enum\ExpenseStatus;
use App\Repository\ExpenseRepository;
use App\Service\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Charge ou dépense (M05).
 *
 * L'approbation est distincte de la création : un utilisateur ne peut pas
 * approuver sa propre dépense.
 */
#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Table(name: 'expenses')]
#[ORM\UniqueConstraint(name: 'uniq_expenses_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_expenses_supplier', columns: ['supplier_id'])]
#[ORM\Index(name: 'idx_expenses_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_expenses_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence de dépense existe déjà.')]
class Expense
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $reference = '';

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ExpenseCategory::class)]
    private ExpenseCategory $category = ExpenseCategory::Other;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ExpenseStatus::class)]
    private ExpenseStatus $status = ExpenseStatus::Draft;

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    private string $label = '';

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Supplier $supplier = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Contract::class)]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Contract $contract = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $expenseDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $amountHt = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4)]
    private string $vatRate = '19.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $vatAmount = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $amountTtc = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $paidAmount = '0.000';

    #[ORM\Column(type: Types::STRING, length: 3)]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Code devise attendu sur trois lettres majuscules.')]
    private string $currencyCode = 'TND';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $attachmentPath = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approved_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $rejectionReason = null;

    /** @var Collection<int, ExpensePayment> */
    #[ORM\OneToMany(targetEntity: ExpensePayment::class, mappedBy: 'expense', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payments;

    public function __construct()
    {
        $this->ensureId();
        $this->payments = new ArrayCollection();
        $this->expenseDate = new \DateTimeImmutable('today');
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

    public function getCategory(): ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(ExpenseCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getStatus(): ExpenseStatus
    {
        return $this->status;
    }

    public function setStatus(ExpenseStatus $status): static
    {
        $this->status = $status;

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

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): static
    {
        $this->contract = $contract;

        return $this;
    }

    public function getExpenseDate(): \DateTimeImmutable
    {
        return $this->expenseDate;
    }

    public function setExpenseDate(\DateTimeImmutable $expenseDate): static
    {
        $this->expenseDate = $expenseDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getAmountHt(): string
    {
        return $this->amountHt;
    }

    public function setAmountHt(string $amountHt): static
    {
        $this->amountHt = Money::normalize($amountHt);
        $this->recomputeTotals();

        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = Money::normalize($vatRate, Money::RATE_SCALE);
        $this->recomputeTotals();

        return $this;
    }

    public function getVatAmount(): string
    {
        return $this->vatAmount;
    }

    public function getAmountTtc(): string
    {
        return $this->amountTtc;
    }

    public function getPaidAmount(): string
    {
        return $this->paidAmount;
    }

    public function getBalance(): string
    {
        return Money::compare($this->paidAmount, $this->amountTtc) >= 0
            ? Money::zero()
            : Money::sub($this->amountTtc, $this->paidAmount);
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $attachmentPath): static
    {
        $this->attachmentPath = $attachmentPath;

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

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    /** @return Collection<int, ExpensePayment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(ExpensePayment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setExpense($this);
        }

        return $this;
    }

    public function recomputeTotals(): void
    {
        $this->vatAmount = Money::percentOf($this->amountHt, $this->vatRate);
        $this->amountTtc = Money::add($this->amountHt, $this->vatAmount);
    }

    public function recomputePaidAmount(): void
    {
        $this->paidAmount = Money::sum(array_map(static fn (ExpensePayment $payment): string => $payment->getAmount(), $this->payments->toArray()));

        if (ExpenseStatus::Draft === $this->status || ExpenseStatus::Cancelled === $this->status || ExpenseStatus::Rejected === $this->status) {
            return;
        }

        if (Money::compare($this->paidAmount, $this->amountTtc) >= 0) {
            $this->status = ExpenseStatus::Paid;
        } elseif (Money::isPositive($this->paidAmount)) {
            $this->status = ExpenseStatus::PartiallyPaid;
        } elseif (ExpenseStatus::PartiallyPaid === $this->status || ExpenseStatus::Paid === $this->status) {
            $this->status = ExpenseStatus::Approved;
        }
    }

    public function submit(): void
    {
        if (ExpenseStatus::Draft !== $this->status) {
            throw new \LogicException('Seule une dépense brouillon peut être soumise.');
        }

        $this->status = ExpenseStatus::Submitted;
    }

    public function approve(User $approver, ?\DateTimeImmutable $at = null): void
    {
        if (null !== $this->createdBy && $this->createdBy->getIdString() === $approver->getIdString()) {
            throw new \LogicException('L’auteur d’une dépense ne peut pas l’approuver lui-même.');
        }

        if (!\in_array($this->status, [ExpenseStatus::Submitted, ExpenseStatus::Draft], true)) {
            throw new \LogicException('Cette dépense n’est pas en attente d’approbation.');
        }

        $this->status = ExpenseStatus::Approved;
        $this->approvedBy = $approver;
        $this->approvedAt = $at ?? new \DateTimeImmutable();
        $this->rejectionReason = null;
    }

    public function reject(User $approver, string $reason, ?\DateTimeImmutable $at = null): void
    {
        if (null !== $this->createdBy && $this->createdBy->getIdString() === $approver->getIdString()) {
            throw new \LogicException('L’auteur d’une dépense ne peut pas la rejeter lui-même.');
        }

        $this->status = ExpenseStatus::Rejected;
        $this->approvedBy = $approver;
        $this->approvedAt = $at ?? new \DateTimeImmutable();
        $this->rejectionReason = $reason;
    }
}
