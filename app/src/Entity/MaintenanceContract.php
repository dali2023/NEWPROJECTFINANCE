<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\BillingFrequency;
use App\Enum\MaintenanceRenewalStatus;
use App\Enum\RenewalMode;
use App\Repository\MaintenanceContractRepository;
use App\Service\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contrat de maintenance récurrent (§15.3).
 *
 * Le contrat est rattaché au contrat initial et conserve sa référence propre.
 * L'historique des versions est porté par les renouvellements et le journal
 * d'audit ; le contrat précédent reste référencé.
 */
#[ORM\Entity(repositoryClass: MaintenanceContractRepository::class)]
#[ORM\Table(name: 'maintenance_contracts')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_contracts_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_maintenance_contract', columns: ['contract_id'])]
#[ORM\Index(name: 'idx_maintenance_end_date', columns: ['maintenance_end_date'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence de maintenance est déjà utilisée.')]
class MaintenanceContract
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 40)]
    #[Assert\NotBlank(message: 'La référence de maintenance est obligatoire.')]
    private string $reference = '';

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'maintenanceContracts')]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    /** Contrat initial ou avenant à l'origine de la maintenance. */
    #[ORM\ManyToOne(targetEntity: Contract::class)]
    #[ORM\JoinColumn(name: 'original_contract_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Contract $originalContract = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $maintenanceStartDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $maintenanceEndDate = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 12])]
    #[Assert\Positive(message: 'La durée doit être supérieure à zéro.')]
    private int $durationMonths = 12;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: BillingFrequency::class)]
    private BillingFrequency $billingFrequency = BillingFrequency::Annual;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le montant périodique ne peut pas être négatif.')]
    private string $periodicAmountHt = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le montant annuel ne peut pas être négatif.')]
    private string $annualAmountHt = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 4, options: ['default' => '19.0000'])]
    private string $vatRate = '19.0000';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $priceRevisionTerms = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le préavis de renouvellement ne peut pas être négatif.')]
    private int $renewalNoticeDays = 0;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: RenewalMode::class)]
    private RenewalMode $renewalMode = RenewalMode::Manual;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: MaintenanceRenewalStatus::class)]
    private MaintenanceRenewalStatus $renewalStatus = MaintenanceRenewalStatus::NotDue;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'commercial_responsible_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $commercialResponsible = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'technical_responsible_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $technicalResponsible = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->ensureId();
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

    public function getOriginalContract(): ?Contract
    {
        return $this->originalContract;
    }

    public function setOriginalContract(?Contract $originalContract): static
    {
        $this->originalContract = $originalContract;

        return $this;
    }

    public function getMaintenanceStartDate(): ?\DateTimeImmutable
    {
        return $this->maintenanceStartDate;
    }

    public function setMaintenanceStartDate(?\DateTimeImmutable $maintenanceStartDate): static
    {
        $this->maintenanceStartDate = $maintenanceStartDate;

        return $this;
    }

    public function getMaintenanceEndDate(): ?\DateTimeImmutable
    {
        return $this->maintenanceEndDate;
    }

    public function setMaintenanceEndDate(?\DateTimeImmutable $maintenanceEndDate): static
    {
        $this->maintenanceEndDate = $maintenanceEndDate;

        return $this;
    }

    public function getDurationMonths(): int
    {
        return $this->durationMonths;
    }

    public function setDurationMonths(int $durationMonths): static
    {
        $this->durationMonths = max(1, $durationMonths);

        return $this;
    }

    public function getBillingFrequency(): BillingFrequency
    {
        return $this->billingFrequency;
    }

    public function setBillingFrequency(BillingFrequency $billingFrequency): static
    {
        $this->billingFrequency = $billingFrequency;

        return $this;
    }

    public function getPeriodicAmountHt(): string
    {
        return $this->periodicAmountHt;
    }

    public function setPeriodicAmountHt(string $periodicAmountHt): static
    {
        $this->periodicAmountHt = $periodicAmountHt;

        return $this;
    }

    public function getAnnualAmountHt(): string
    {
        return $this->annualAmountHt;
    }

    public function setAnnualAmountHt(string $annualAmountHt): static
    {
        $this->annualAmountHt = $annualAmountHt;

        return $this;
    }

    /** Recalcule le montant annuel à partir du montant périodique. */
    public function recomputeAnnualAmount(): void
    {
        if (Money::isZero($this->annualAmountHt) && Money::isPositive($this->periodicAmountHt)) {
            $this->annualAmountHt = Money::mul(
                $this->periodicAmountHt,
                (string) $this->billingFrequency->periodsPerYear(),
            );
        }
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = $vatRate;

        return $this;
    }

    public function getPriceRevisionTerms(): ?string
    {
        return $this->priceRevisionTerms;
    }

    public function setPriceRevisionTerms(?string $priceRevisionTerms): static
    {
        $this->priceRevisionTerms = $priceRevisionTerms;

        return $this;
    }

    public function getRenewalNoticeDays(): int
    {
        return $this->renewalNoticeDays;
    }

    public function setRenewalNoticeDays(int $renewalNoticeDays): static
    {
        $this->renewalNoticeDays = max(0, $renewalNoticeDays);

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

    public function getRenewalStatus(): MaintenanceRenewalStatus
    {
        return $this->renewalStatus;
    }

    public function setRenewalStatus(MaintenanceRenewalStatus $renewalStatus): static
    {
        $this->renewalStatus = $renewalStatus;

        return $this;
    }

    public function getCommercialResponsible(): ?User
    {
        return $this->commercialResponsible;
    }

    public function setCommercialResponsible(?User $commercialResponsible): static
    {
        $this->commercialResponsible = $commercialResponsible;

        return $this;
    }

    public function getTechnicalResponsible(): ?User
    {
        return $this->technicalResponsible;
    }

    public function setTechnicalResponsible(?User $technicalResponsible): static
    {
        $this->technicalResponsible = $technicalResponsible;

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

    /** Date limite de préparation du renouvellement de maintenance. */
    public function getRenewalPreparationDate(): ?\DateTimeImmutable
    {
        if (null === $this->maintenanceEndDate) {
            return null;
        }

        $notice = $this->renewalNoticeDays > 0 ? $this->renewalNoticeDays : ($this->contract?->getNoticePeriodDays() ?? 0);

        return $this->maintenanceEndDate->modify(sprintf('-%d days', $notice));
    }

    public function isActive(?\DateTimeImmutable $today = null): bool
    {
        $today ??= new \DateTimeImmutable('today');

        return (null === $this->maintenanceStartDate || $this->maintenanceStartDate <= $today)
            && (null === $this->maintenanceEndDate || $this->maintenanceEndDate >= $today);
    }

    public function __toString(): string
    {
        return $this->reference;
    }
}
