<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\SoftDeletableTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ContractStatus;
use App\Enum\ContractType;
use App\Enum\RenewalMode;
use App\Repository\ContractRepository;
use App\Service\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fiche contrat indépendante (§15.2, écran 14).
 *
 * La fiche est rattachée à un client et, facultativement, à un projet. Les
 * montants restent des chaînes décimales calculées avec bcmath (§5.1) : aucun
 * flottant binaire n'intervient.
 */
#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\Table(name: 'contracts')]
#[ORM\UniqueConstraint(name: 'uniq_contracts_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_contracts_client', columns: ['client_id'])]
#[ORM\Index(name: 'idx_contracts_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_contracts_status', columns: ['status'])]
#[ORM\Index(name: 'idx_contracts_end_date', columns: ['effective_end_date'])]
#[ORM\Index(name: 'idx_contracts_responsible', columns: ['responsible_user_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence de contrat est déjà utilisée.')]
class Contract
{
    use UuidIdTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(type: Types::STRING, length: 40)]
    #[Assert\NotBlank(message: 'La référence du contrat est obligatoire.')]
    private string $reference = '';

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'L’intitulé du contrat est obligatoire.')]
    private string $title = '';

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ContractType::class)]
    private ContractType $contractType = ContractType::Private;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Le client est obligatoire.')]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** Numéro du marché ou de la commande. */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $marketReference = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $signatureDate = null;

    /** Date de prise d'effet juridique. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveStartDate = null;

    /** Date de début d'exécution opérationnelle. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $executionStartDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveEndDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le montant HT ne peut pas être négatif.')]
    private string $amountHt = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 4, options: ['default' => '19.0000'])]
    #[Assert\PositiveOrZero(message: 'Le taux de TVA ne peut pas être négatif.')]
    #[Assert\LessThanOrEqual(100, message: 'Le taux de TVA ne peut pas dépasser 100 %.')]
    private string $vatRate = '19.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    private string $vatAmount = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    private string $amountTtc = '0.000';

    #[ORM\Column(type: Types::STRING, length: 3, options: ['default' => 'TND'])]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Devise invalide : code ISO à trois lettres attendu.')]
    private string $currencyCode = 'TND';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'responsible_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $responsible = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $paymentTerms = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $renewalTerms = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le préavis ne peut pas être négatif.')]
    private int $noticePeriodDays = 0;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: RenewalMode::class)]
    private RenewalMode $renewalMode = RenewalMode::Manual;

    /**
     * Durée contractuelle de garantie, utilisée pour calculer la date
     * indicative de réception définitive (§15.10 B).
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'La durée de garantie ne peut pas être négative.')]
    private int $warrantyDurationDays = 0;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: ContractStatus::class)]
    private ContractStatus $status = ContractStatus::Draft;

    #[ORM\ManyToOne(targetEntity: ContractDocument::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ContractDocument $mainDocument = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, MaintenanceContract> */
    #[ORM\OneToMany(targetEntity: MaintenanceContract::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $maintenanceContracts;

    /** @var Collection<int, ContractMilestone> */
    #[ORM\OneToMany(targetEntity: ContractMilestone::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['plannedDate' => 'ASC', 'reference' => 'ASC'])]
    private Collection $milestones;

    /** @var Collection<int, ContractReceipt> */
    #[ORM\OneToMany(targetEntity: ContractReceipt::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $receipts;

    /** @var Collection<int, ContractRenewal> */
    #[ORM\OneToMany(targetEntity: ContractRenewal::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $renewals;

    /** @var Collection<int, RetentionGuarantee> */
    #[ORM\OneToMany(targetEntity: RetentionGuarantee::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $retentionGuarantees;

    /** @var Collection<int, ContractNotification> */
    #[ORM\OneToMany(targetEntity: ContractNotification::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $notifications;

    /** @var Collection<int, ContractDocument> */
    #[ORM\OneToMany(targetEntity: ContractDocument::class, mappedBy: 'contract', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $documents;

    public function __construct()
    {
        $this->ensureId();
        $this->maintenanceContracts = new ArrayCollection();
        $this->milestones = new ArrayCollection();
        $this->receipts = new ArrayCollection();
        $this->renewals = new ArrayCollection();
        $this->retentionGuarantees = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->documents = new ArrayCollection();
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getContractType(): ContractType
    {
        return $this->contractType;
    }

    public function setContractType(ContractType $contractType): static
    {
        $this->contractType = $contractType;

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

    public function getMarketReference(): ?string
    {
        return $this->marketReference;
    }

    public function setMarketReference(?string $marketReference): static
    {
        $this->marketReference = $marketReference;

        return $this;
    }

    public function getSignatureDate(): ?\DateTimeImmutable
    {
        return $this->signatureDate;
    }

    public function setSignatureDate(?\DateTimeImmutable $signatureDate): static
    {
        $this->signatureDate = $signatureDate;

        return $this;
    }

    public function getEffectiveStartDate(): ?\DateTimeImmutable
    {
        return $this->effectiveStartDate;
    }

    public function setEffectiveStartDate(?\DateTimeImmutable $effectiveStartDate): static
    {
        $this->effectiveStartDate = $effectiveStartDate;

        return $this;
    }

    public function getExecutionStartDate(): ?\DateTimeImmutable
    {
        return $this->executionStartDate;
    }

    public function setExecutionStartDate(?\DateTimeImmutable $executionStartDate): static
    {
        $this->executionStartDate = $executionStartDate;

        return $this;
    }

    public function getEffectiveEndDate(): ?\DateTimeImmutable
    {
        return $this->effectiveEndDate;
    }

    public function setEffectiveEndDate(?\DateTimeImmutable $effectiveEndDate): static
    {
        $this->effectiveEndDate = $effectiveEndDate;

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

    /** §15.2 : montant TTC = HT + TVA, TVA = HT × taux / 100. */
    public function recomputeTotals(): void
    {
        $this->vatAmount = Money::percentOf($this->amountHt, $this->vatRate);
        $this->amountTtc = Money::add($this->amountHt, $this->vatAmount);
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

    public function getResponsible(): ?User
    {
        return $this->responsible;
    }

    public function setResponsible(?User $responsible): static
    {
        $this->responsible = $responsible;

        return $this;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): static
    {
        $this->paymentTerms = $paymentTerms;

        return $this;
    }

    public function getRenewalTerms(): ?string
    {
        return $this->renewalTerms;
    }

    public function setRenewalTerms(?string $renewalTerms): static
    {
        $this->renewalTerms = $renewalTerms;

        return $this;
    }

    public function getNoticePeriodDays(): int
    {
        return $this->noticePeriodDays;
    }

    public function setNoticePeriodDays(int $noticePeriodDays): static
    {
        $this->noticePeriodDays = max(0, $noticePeriodDays);

        return $this;
    }

    public function getRenewalMode(): RenewalMode
    {
        return $this->renewalMode;
    }

    public function setRenewalMode(RenewalMode $renewalMode): static
    {
        $this->renewalMode = $renewalMode;

        return $this;
    }

    public function getWarrantyDurationDays(): int
    {
        return $this->warrantyDurationDays;
    }

    public function setWarrantyDurationDays(int $warrantyDurationDays): static
    {
        $this->warrantyDurationDays = max(0, $warrantyDurationDays);

        return $this;
    }

    public function getStatus(): ContractStatus
    {
        return $this->status;
    }

    public function setStatus(ContractStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMainDocument(): ?ContractDocument
    {
        return $this->mainDocument;
    }

    public function setMainDocument(?ContractDocument $mainDocument): static
    {
        $this->mainDocument = $mainDocument;

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

    /**
     * Date limite de préparation du renouvellement (§15.10 A) :
     * date d'expiration − préavis contractuel.
     */
    public function getRenewalPreparationDate(): ?\DateTimeImmutable
    {
        if (null === $this->effectiveEndDate) {
            return null;
        }

        return $this->effectiveEndDate->modify(sprintf('-%d days', $this->noticePeriodDays));
    }

    /** @return Collection<int, MaintenanceContract> */
    public function getMaintenanceContracts(): Collection
    {
        return $this->maintenanceContracts;
    }

    public function addMaintenanceContract(MaintenanceContract $maintenanceContract): static
    {
        if (!$this->maintenanceContracts->contains($maintenanceContract)) {
            $this->maintenanceContracts->add($maintenanceContract);
            $maintenanceContract->setContract($this);
        }

        return $this;
    }

    public function removeMaintenanceContract(MaintenanceContract $maintenanceContract): static
    {
        $this->maintenanceContracts->removeElement($maintenanceContract);

        return $this;
    }

    public function getActiveMaintenanceContract(?\DateTimeImmutable $today = null): ?MaintenanceContract
    {
        $today ??= new \DateTimeImmutable('today');
        foreach ($this->maintenanceContracts as $maintenanceContract) {
            if (null !== $maintenanceContract->getMaintenanceEndDate()
                && $maintenanceContract->getMaintenanceEndDate() >= $today) {
                return $maintenanceContract;
            }
        }

        return null;
    }

    /** @return Collection<int, ContractMilestone> */
    public function getMilestones(): Collection
    {
        return $this->milestones;
    }

    public function addMilestone(ContractMilestone $milestone): static
    {
        if (!$this->milestones->contains($milestone)) {
            $this->milestones->add($milestone);
            $milestone->setContract($this);
            if (null === $milestone->getProject()) {
                $milestone->setProject($this->project);
            }
        }

        return $this;
    }

    public function removeMilestone(ContractMilestone $milestone): static
    {
        $this->milestones->removeElement($milestone);

        return $this;
    }

    /** @return Collection<int, ContractReceipt> */
    public function getReceipts(): Collection
    {
        return $this->receipts;
    }

    public function addReceipt(ContractReceipt $receipt): static
    {
        if (!$this->receipts->contains($receipt)) {
            $this->receipts->add($receipt);
            $receipt->setContract($this);
            if (null === $receipt->getProject()) {
                $receipt->setProject($this->project);
            }
        }

        return $this;
    }

    public function removeReceipt(ContractReceipt $receipt): static
    {
        $this->receipts->removeElement($receipt);

        return $this;
    }

    /** @return Collection<int, ContractRenewal> */
    public function getRenewals(): Collection
    {
        return $this->renewals;
    }

    public function addRenewal(ContractRenewal $renewal): static
    {
        if (!$this->renewals->contains($renewal)) {
            $this->renewals->add($renewal);
            $renewal->setContract($this);
        }

        return $this;
    }

    public function removeRenewal(ContractRenewal $renewal): static
    {
        $this->renewals->removeElement($renewal);

        return $this;
    }

    /** @return Collection<int, RetentionGuarantee> */
    public function getRetentionGuarantees(): Collection
    {
        return $this->retentionGuarantees;
    }

    public function addRetentionGuarantee(RetentionGuarantee $guarantee): static
    {
        if (!$this->retentionGuarantees->contains($guarantee)) {
            $this->retentionGuarantees->add($guarantee);
            $guarantee->setContract($this);
            if (null === $guarantee->getProject()) {
                $guarantee->setProject($this->project);
            }
        }

        return $this;
    }

    public function removeRetentionGuarantee(RetentionGuarantee $guarantee): static
    {
        $this->retentionGuarantees->removeElement($guarantee);

        return $this;
    }

    /** @return Collection<int, ContractNotification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(ContractNotification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setContract($this);
        }

        return $this;
    }

    public function removeNotification(ContractNotification $notification): static
    {
        $this->notifications->removeElement($notification);

        return $this;
    }

    /** @return Collection<int, ContractDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(ContractDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setContract($this);
        }

        return $this;
    }

    public function removeDocument(ContractDocument $document): static
    {
        $this->documents->removeElement($document);

        return $this;
    }

    /** Somme des montants HT des échéances non annulées. */
    public function getMilestonesTotalHt(): string
    {
        return Money::sum(array_map(
            static fn (ContractMilestone $milestone): string => $milestone->getAmountHt(),
            array_filter(
                $this->milestones->toArray(),
                static fn (ContractMilestone $milestone): bool => !$milestone->isCancelled(),
            ),
        ));
    }

    /** Somme déjà facturée sur les échéances du contrat. */
    public function getInvoicedTotalHt(): string
    {
        return Money::sum(array_map(
            static fn (ContractMilestone $milestone): string => $milestone->getBilledAmountHt(),
            $this->milestones->toArray(),
        ));
    }

    public function isResponsibleFor(User $user): bool
    {
        $userId = $user->getIdString();
        if ('' === $userId) {
            return false;
        }

        if ($this->responsible?->getIdString() === $userId || $this->createdBy?->getIdString() === $userId) {
            return true;
        }

        if ($this->project?->getManager()?->getIdString() === $userId) {
            return true;
        }

        foreach ($this->project?->getMembers() ?? [] as $member) {
            if ($member->getIdString() === $userId) {
                return true;
            }
        }

        foreach ($this->maintenanceContracts as $maintenanceContract) {
            if ($maintenanceContract->getCommercialResponsible()?->getIdString() === $userId
                || $maintenanceContract->getTechnicalResponsible()?->getIdString() === $userId) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->reference.' — '.$this->title;
    }
}
