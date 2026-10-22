<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\FiscalYearStatus;
use App\Repository\FiscalYearRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Exercice financier (§4.1, §5.2 C).
 */
#[ORM\Entity(repositoryClass: FiscalYearRepository::class)]
#[ORM\Table(name: 'fiscal_years')]
#[ORM\UniqueConstraint(name: 'uniq_fiscal_years_label', columns: ['label'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['label'], message: 'Cet exercice existe déjà.')]
class FiscalYear
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: 'Le libellé de l’exercice est obligatoire.')]
    private string $label = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date d’ouverture est obligatoire.')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de clôture est obligatoire.')]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FiscalYearStatus::class)]
    private FiscalYearStatus $status = FiscalYearStatus::Open;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'closed_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->ensureId();
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

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): FiscalYearStatus
    {
        return $this->status;
    }

    public function setStatus(FiscalYearStatus $status): static
    {
        $this->status = $status;
        if (FiscalYearStatus::Open === $status) {
            $this->closedAt = null;
            $this->closedBy = null;
        }

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function close(User $by): static
    {
        $this->status = FiscalYearStatus::Closed;
        $this->closedAt = new \DateTimeImmutable();
        $this->closedBy = $by;

        return $this;
    }

    /** Un exercice clôturé interdit les écritures ordinaires (§5.4). */
    public function acceptsOrdinaryWrites(): bool
    {
        return $this->status->acceptsOrdinaryWrites();
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

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null === $this->startDate || null === $this->endDate) {
            return;
        }

        if ($this->endDate <= $this->startDate) {
            $context->buildViolation('La date de clôture doit être postérieure à la date d’ouverture.')
                ->atPath('endDate')
                ->addViolation();
        }
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
