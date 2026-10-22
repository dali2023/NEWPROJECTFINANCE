<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\CompletionStatus;
use App\Enum\ContractBillingStatus;
use App\Enum\MilestoneType;
use App\Repository\ContractMilestoneRepository;
use App\Service\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Échéance contractuelle de facturation (§15.6).
 *
 * Deux statuts indépendants sont suivis : la réalisation de l'événement et la
 * facturation. « Prête à facturer » ne vaut jamais « facturée ».
 *
 * La clé `generationKey` garantit l'idempotence de la génération périodique :
 * une même période ne peut produire deux échéances.
 */
#[ORM\Entity(repositoryClass: ContractMilestoneRepository::class)]
#[ORM\Table(name: 'contract_milestones')]
#[ORM\UniqueConstraint(name: 'uniq_contract_milestones_reference', columns: ['reference'])]
#[ORM\UniqueConstraint(name: 'uniq_contract_milestones_generation', columns: ['generation_key'])]
#[ORM\Index(name: 'idx_contract_milestones_contract', columns: ['contract_id'])]
#[ORM\Index(name: 'idx_contract_milestones_planned', columns: ['planned_date'])]
#[ORM\Index(name: 'idx_contract_milestones_billing', columns: ['billing_status'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence d’échéance est déjà utilisée.')]
class ContractMilestone
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 40)]
    #[Assert\NotBlank(message: 'La référence de l’échéance est obligatoire.')]
    private string $reference = '';

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'milestones')]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: MaintenanceContract::class)]
    #[ORM\JoinColumn(name: 'maintenance_contract_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaintenanceContract $maintenanceContract = null;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: MilestoneType::class)]
    private MilestoneType $milestoneType = MilestoneType::Other;

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'Le libellé de l’échéance est obligatoire.')]
    private string $label = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $plannedDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le montant prévu ne peut pas être négatif.')]
    private string $amountHt = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 4, options: ['default' => '19.0000'])]
    private string $vatRate = '19.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    private string $vatAmount = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    private string $amountTtc = '0.000';

    /** Montant HT effectivement facturé, éventuellement partiel. */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    private string $billedAmountHt = '0.000';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $invoiceReference = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $invoiceDate = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: CompletionStatus::class)]
    private CompletionStatus $completionStatus = CompletionStatus::Upcoming;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ContractBillingStatus::class)]
    private ContractBillingStatus $billingStatus = ContractBillingStatus::NotBillable;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $blockedReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'responsible_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $responsible = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** Clé stable d'idempotence pour les échéances générées automatiquement. */
    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    private ?string $generationKey = null;

    /** @var Collection<int, ContractMilestoneHistory> */
    #[ORM\OneToMany(targetEntity: ContractMilestoneHistory::class, mappedBy: 'milestone', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['changedAt' => 'DESC'])]
    private Collection $history;

    public function __construct()
    {
        $this->ensureId();
        $this->history = new ArrayCollection();
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

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): static
    {
        $this->contract = $contract;

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

    public function getMaintenanceContract(): ?MaintenanceContract
    {
        return $this->maintenanceContract;
    }

    public function setMaintenanceContract(?MaintenanceContract $maintenanceContract): static
    {
        $this->maintenanceContract = $maintenanceContract;

        return $this;
    }

    public function getMilestoneType(): MilestoneType
    {
        return $this->milestoneType;
    }

    public function setMilestoneType(MilestoneType $milestoneType): static
    {
        $this->milestoneType = $milestoneType;

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

    public function getPlannedDate(): ?\DateTimeImmutable
    {
        return $this->plannedDate;
    }

    public function setPlannedDate(?\DateTimeImmutable $plannedDate): static
    {
        $this->plannedDate = $plannedDate;

        return $this;
    }

    public function getActualDate(): ?\DateTimeImmutable
    {
        return $this->actualDate;
    }

    public function setActualDate(?\DateTimeImmutable $actualDate): static
    {
        $this->actualDate = $actualDate;

        return $this;
    }

    public function getAmountHt(): string
    {
        return $this->amountHt;
    }

    public function setAmountHt(string $amountHt): static
    {
        $this->amountHt = $amountHt;
        $this->recomputeTotals();

        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = $vatRate;
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

    public function recomputeTotals(): void
    {
        $this->vatAmount = Money::percentOf($this->amountHt, $this->vatRate);
        $this->amountTtc = Money::add($this->amountHt, $this->vatAmount);
    }

    public function getBilledAmountHt(): string
    {
        return $this->billedAmountHt;
    }

    public function setBilledAmountHt(string $billedAmountHt): static
    {
        $this->billedAmountHt = $billedAmountHt;
        $this->refreshBillingStatus();

        return $this;
    }

    public function getInvoiceReference(): ?string
    {
        return $this->invoiceReference;
    }

    public function setInvoiceReference(?string $invoiceReference): static
    {
        $this->invoiceReference = $invoiceReference;

        return $this;
    }

    public function getInvoiceDate(): ?\DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?\DateTimeImmutable $invoiceDate): static
    {
        $this->invoiceDate = $invoiceDate;

        return $this;
    }

    public function getCompletionStatus(): CompletionStatus
    {
        return $this->completionStatus;
    }

    public function setCompletionStatus(CompletionStatus $completionStatus): static
    {
        $this->completionStatus = $completionStatus;

        return $this;
    }

    public function getBillingStatus(): ContractBillingStatus
    {
        return $this->billingStatus;
    }

    public function setBillingStatus(ContractBillingStatus $billingStatus): static
    {
        $this->billingStatus = $billingStatus;

        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function getBlockedReason(): ?string
    {
        return $this->blockedReason;
    }

    public function setBlockedReason(?string $blockedReason): static
    {
        $this->blockedReason = $blockedReason;

        return $this;
    }

    public function getResponsible(): ?User
    {
        return $this->responsible;
    }

    public function setResponsible(?User $responsible): static
    {
        $this->responsible = $responsible;

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

    public function getGenerationKey(): ?string
    {
        return $this->generationKey;
    }

    public function setGenerationKey(?string $generationKey): static
    {
        $this->generationKey = $generationKey;

        return $this;
    }

    /** @return Collection<int, ContractMilestoneHistory> */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(ContractMilestoneHistory $history): static
    {
        if (!$this->history->contains($history)) {
            $this->history->add($history);
            $history->setMilestone($this);
        }

        return $this;
    }

    public function isCancelled(): bool
    {
        return CompletionStatus::Cancelled === $this->completionStatus;
    }

    public function isSuspended(): bool
    {
        return CompletionStatus::Suspended === $this->completionStatus;
    }

    public function isFullyInvoiced(): bool
    {
        return ContractBillingStatus::FullyInvoiced === $this->billingStatus
            || Money::compare($this->billedAmountHt, $this->amountHt) >= 0;
    }

    public function getRemainingToInvoiceHt(): string
    {
        if (Money::compare($this->billedAmountHt, $this->amountHt) >= 0) {
            return Money::zero();
        }

        return Money::sub($this->amountHt, $this->billedAmountHt);
    }

    /** Échéance en retard (§15.10 D). */
    public function isOverdue(?\DateTimeImmutable $today = null): bool
    {
        $today ??= new \DateTimeImmutable('today');

        return null !== $this->plannedDate
            && $this->plannedDate < $today
            && !$this->completionStatus->blocksBilling()
            && !$this->billingStatus->blocksBilling()
            && !$this->isFullyInvoiced()
            && Money::isPositive($this->getRemainingToInvoiceHt());
    }

    /** Met à jour le statut de facturation en fonction du montant facturé. */
    public function refreshBillingStatus(): void
    {
        if (ContractBillingStatus::Blocked === $this->billingStatus) {
            return;
        }

        if (Money::isZero($this->billedAmountHt)) {
            return;
        }

        if (Money::compare($this->billedAmountHt, $this->amountHt) >= 0) {
            $this->billingStatus = ContractBillingStatus::FullyInvoiced;

            return;
        }

        $this->billingStatus = ContractBillingStatus::PartiallyInvoiced;
    }

    /** Enregistre une facturation, totale ou partielle, sans dépasser le montant prévu. */
    public function invoice(string $amountHt, string $invoiceReference, ?\DateTimeImmutable $invoiceDate = null): void
    {
        $remaining = $this->getRemainingToInvoiceHt();
        $amount = Money::min(Money::normalize($amountHt), $remaining);

        $this->billedAmountHt = Money::add($this->billedAmountHt, $amount);
        $this->invoiceReference = $invoiceReference;
        $this->invoiceDate = $invoiceDate ?? new \DateTimeImmutable('today');
        $this->refreshBillingStatus();

        if (Money::compare($this->billedAmountHt, $this->amountHt) >= 0) {
            $this->billingStatus = ContractBillingStatus::FullyInvoiced;
        }
    }

    public function __toString(): string
    {
        return $this->reference.' — '.$this->label;
    }
}
