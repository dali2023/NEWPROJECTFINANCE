<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Repository\InvoiceRepository;
use App\Service\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Facture client (M04).
 *
 * Les montants sont stockés en chaînes décimales et recalculés avec bcmath.
 * Une facture validée n'est plus modifiable ; les règlements et les avoirs
 * réduisent son solde sans jamais modifier le montant facturé.
 */
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
#[ORM\UniqueConstraint(name: 'uniq_invoices_number', columns: ['number'])]
#[ORM\Index(name: 'idx_invoices_client', columns: ['client_id'])]
#[ORM\Index(name: 'idx_invoices_status', columns: ['status'])]
#[ORM\Index(name: 'idx_invoices_contract', columns: ['contract_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['number'], message: 'Ce numéro de facture existe déjà.')]
class Invoice
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $number = '';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: InvoiceType::class)]
    private InvoiceType $invoiceType = InvoiceType::Invoice;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status = InvoiceStatus::Draft;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Contract::class)]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Contract $contract = null;

    #[ORM\ManyToOne(targetEntity: ContractMilestone::class)]
    #[ORM\JoinColumn(name: 'contract_milestone_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ContractMilestone $contractMilestone = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_invoice_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parentInvoice = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(type: Types::STRING, length: 3)]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Code devise attendu sur trois lettres majuscules.')]
    private string $currencyCode = 'TND';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $amountHt = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $vatAmount = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $amountTtc = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $paidAmount = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $creditedAmount = '0.000';

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
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'cancelled_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $cancellationReason = null;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'invoice')]
    private Collection $payments;

    public function __construct()
    {
        $this->ensureId();
        $this->lines = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->issueDate = new \DateTimeImmutable('today');
        $this->dueDate = new \DateTimeImmutable('+30 days');
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = strtoupper(trim($number));

        return $this;
    }

    public function getInvoiceType(): InvoiceType
    {
        return $this->invoiceType;
    }

    public function setInvoiceType(InvoiceType $invoiceType): static
    {
        $this->invoiceType = $invoiceType;

        return $this;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

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

    public function getContractMilestone(): ?ContractMilestone
    {
        return $this->contractMilestone;
    }

    public function setContractMilestone(?ContractMilestone $contractMilestone): static
    {
        $this->contractMilestone = $contractMilestone;

        return $this;
    }

    public function getParentInvoice(): ?self
    {
        return $this->parentInvoice;
    }

    public function setParentInvoice(?self $parentInvoice): static
    {
        $this->parentInvoice = $parentInvoice;

        return $this;
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

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

    public function getAmountHt(): string
    {
        return $this->amountHt;
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

    public function getCreditedAmount(): string
    {
        return $this->creditedAmount;
    }

    public function getBalance(): string
    {
        $settled = Money::add($this->paidAmount, $this->creditedAmount);

        return Money::compare($settled, $this->amountTtc) >= 0
            ? Money::zero()
            : Money::sub($this->amountTtc, $settled);
    }

    public function isOverdue(?\DateTimeImmutable $today = null): bool
    {
        $today ??= new \DateTimeImmutable('today');

        return $this->status->isOpen() && $this->dueDate < $today && Money::isPositive($this->getBalance());
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

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    /** @return Collection<int, InvoiceLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(InvoiceLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
            $line->setPosition($this->lines->count());
        }

        return $this;
    }

    public function removeLine(InvoiceLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setInvoice($this);
        }

        return $this;
    }

    public function recomputeTotals(): void
    {
        $this->amountHt = Money::sum(array_map(static fn (InvoiceLine $line): string => $line->getAmountHt(), $this->lines->toArray()));
        $this->vatAmount = Money::sum(array_map(static fn (InvoiceLine $line): string => $line->getVatAmount(), $this->lines->toArray()));
        $this->amountTtc = Money::add($this->amountHt, $this->vatAmount);
    }

    public function recomputePaidAmount(): void
    {
        $this->paidAmount = Money::sum(array_map(static fn (Payment $payment): string => $payment->getAmount(), $this->payments->toArray()));
        $this->refreshPaymentStatus();
    }

    public function refreshPaymentStatus(): void
    {
        if (\in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
            return;
        }

        $settled = Money::add($this->paidAmount, $this->creditedAmount);
        if (Money::compare($settled, $this->amountTtc) >= 0) {
            $this->status = InvoiceStatus::Paid;
        } elseif (Money::isPositive($this->paidAmount)) {
            $this->status = InvoiceStatus::PartiallyPaid;
        } elseif (InvoiceStatus::PartiallyPaid === $this->status || InvoiceStatus::Paid === $this->status) {
            $this->status = InvoiceStatus::Validated;
        }
    }

    public function validate(User $user, ?\DateTimeImmutable $at = null): void
    {
        if (InvoiceStatus::Draft !== $this->status) {
            throw new \LogicException('Seule une facture brouillon peut être validée.');
        }

        if ($this->lines->isEmpty()) {
            throw new \LogicException('Une facture doit comporter au moins une ligne.');
        }

        $this->recomputeTotals();
        $this->status = InvoiceStatus::Validated;
        $this->validatedBy = $user;
        $this->validatedAt = $at ?? new \DateTimeImmutable();
    }

    public function markSent(?\DateTimeImmutable $at = null): void
    {
        if (!$this->status->isOpen()) {
            return;
        }

        $this->sentAt = $at ?? new \DateTimeImmutable();
        if (InvoiceStatus::Validated === $this->status) {
            $this->status = InvoiceStatus::Sent;
        }
    }

    public function cancel(User $user, string $reason, ?\DateTimeImmutable $at = null): void
    {
        if (InvoiceStatus::Draft !== $this->status) {
            throw new \LogicException('Seule une facture brouillon peut être annulée. Utilisez un avoir après validation.');
        }

        if (InvoiceStatus::Paid === $this->status || Money::isPositive($this->paidAmount)) {
            throw new \LogicException('Une facture réglée ou partiellement réglée ne peut pas être annulée.');
        }

        $this->status = InvoiceStatus::Cancelled;
        $this->cancelledBy = $user;
        $this->cancelledAt = $at ?? new \DateTimeImmutable();
        $this->cancellationReason = $reason;
    }
}
