<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\RetentionStatus;
use App\Repository\RetentionGuaranteeRepository;
use App\Service\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Retenue de garantie (§15.5, §15.10 F).
 *
 * La libération est suivie séparément de la facturation et de l'encaissement :
 * date d'éligibilité, demande de libération et versement effectif restent trois
 * informations distinctes.
 */
#[ORM\Entity(repositoryClass: RetentionGuaranteeRepository::class)]
#[ORM\Table(name: 'retention_guarantees')]
#[ORM\Index(name: 'idx_retention_contract', columns: ['contract_id'])]
#[ORM\Index(name: 'idx_retention_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class RetentionGuarantee
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'retentionGuarantees')]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le montant de la retenue ne peut pas être négatif.')]
    private string $amount = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    private string $releasedAmount = '0.000';

    #[ORM\Column(type: Types::STRING, length: 3, options: ['default' => 'TND'])]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Devise invalide : code ISO à trois lettres attendu.')]
    private string $currencyCode = 'TND';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $eligibilityDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $releaseRequestDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualReleaseDate = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: RetentionStatus::class)]
    private RetentionStatus $status = RetentionStatus::Pending;

    #[ORM\ManyToOne(targetEntity: ContractDocument::class)]
    #[ORM\JoinColumn(name: 'supporting_document_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ContractDocument $supportingDocument = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->ensureId();
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

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getReleasedAmount(): string
    {
        return $this->releasedAmount;
    }

    public function setReleasedAmount(string $releasedAmount): static
    {
        $this->releasedAmount = $releasedAmount;

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

    public function getEligibilityDate(): ?\DateTimeImmutable
    {
        return $this->eligibilityDate;
    }

    public function setEligibilityDate(?\DateTimeImmutable $eligibilityDate): static
    {
        $this->eligibilityDate = $eligibilityDate;

        return $this;
    }

    public function getReleaseRequestDate(): ?\DateTimeImmutable
    {
        return $this->releaseRequestDate;
    }

    public function setReleaseRequestDate(?\DateTimeImmutable $releaseRequestDate): static
    {
        $this->releaseRequestDate = $releaseRequestDate;

        return $this;
    }

    public function getActualReleaseDate(): ?\DateTimeImmutable
    {
        return $this->actualReleaseDate;
    }

    public function setActualReleaseDate(?\DateTimeImmutable $actualReleaseDate): static
    {
        $this->actualReleaseDate = $actualReleaseDate;

        return $this;
    }

    public function getStatus(): RetentionStatus
    {
        return $this->status;
    }

    public function setStatus(RetentionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSupportingDocument(): ?ContractDocument
    {
        return $this->supportingDocument;
    }

    public function setSupportingDocument(?ContractDocument $supportingDocument): static
    {
        $this->supportingDocument = $supportingDocument;

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

    /** Montant restant à libérer = montant retenu − montant déjà libéré. */
    public function getRemainingToRelease(): string
    {
        if (Money::compare($this->releasedAmount, $this->amount) >= 0) {
            return Money::zero();
        }

        return Money::sub($this->amount, $this->releasedAmount);
    }

    public function isEligible(?\DateTimeImmutable $today = null): bool
    {
        $today ??= new \DateTimeImmutable('today');

        return null !== $this->eligibilityDate && $this->eligibilityDate <= $today;
    }

    /** Enregistre la demande de libération, sans confondre avec le versement. */
    public function requestRelease(?\DateTimeImmutable $date = null): void
    {
        $this->releaseRequestDate = $date ?? new \DateTimeImmutable('today');
        if (RetentionStatus::Released !== $this->status) {
            $this->status = RetentionStatus::ReleaseRequested;
        }
    }

    /** Enregistre le versement effectif et recalcule le solde. */
    public function release(string $amount, ?\DateTimeImmutable $date = null): void
    {
        $remaining = $this->getRemainingToRelease();
        $released = Money::min(Money::normalize($amount), $remaining);
        $this->releasedAmount = Money::add($this->releasedAmount, $released);
        $this->actualReleaseDate = $date ?? new \DateTimeImmutable('today');

        if (Money::compare($this->releasedAmount, $this->amount) >= 0) {
            $this->status = RetentionStatus::Released;
        } elseif (Money::isPositive($this->releasedAmount)) {
            $this->status = RetentionStatus::ReleaseRequested;
        }
    }
}
