<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ReceiptType;
use App\Enum\ReservationsStatus;
use App\Repository\ContractReceiptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réception provisoire ou définitive (§15.5).
 *
 * Une réception définitive n'est considérée réalisée qu'après enregistrement de
 * la date effective, du procès-verbal et validation par un utilisateur habilité.
 */
#[ORM\Entity(repositoryClass: ContractReceiptRepository::class)]
#[ORM\Table(name: 'contract_receipts')]
#[ORM\Index(name: 'idx_contract_receipts_contract', columns: ['contract_id'])]
#[ORM\Index(name: 'idx_contract_receipts_type', columns: ['receipt_type'])]
#[ORM\HasLifecycleCallbacks]
class ContractReceipt
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'receipts')]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ReceiptType::class)]
    private ReceiptType $receiptType = ReceiptType::Provisional;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $plannedDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualDate = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $pvReference = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $warrantyStartDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $warrantyEndDate = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ReservationsStatus::class)]
    private ReservationsStatus $reservationsStatus = ReservationsStatus::None;

    #[ORM\ManyToOne(targetEntity: ContractDocument::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ContractDocument $document = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'validated_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, ContractReservation> */
    #[ORM\OneToMany(targetEntity: ContractReservation::class, mappedBy: 'receipt', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reservations;

    public function __construct()
    {
        $this->ensureId();
        $this->reservations = new ArrayCollection();
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

    public function getReceiptType(): ReceiptType
    {
        return $this->receiptType;
    }

    public function setReceiptType(ReceiptType $receiptType): static
    {
        $this->receiptType = $receiptType;

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

    public function getPvReference(): ?string
    {
        return $this->pvReference;
    }

    public function setPvReference(?string $pvReference): static
    {
        $this->pvReference = $pvReference;

        return $this;
    }

    public function getWarrantyStartDate(): ?\DateTimeImmutable
    {
        return $this->warrantyStartDate;
    }

    public function setWarrantyStartDate(?\DateTimeImmutable $warrantyStartDate): static
    {
        $this->warrantyStartDate = $warrantyStartDate;

        return $this;
    }

    public function getWarrantyEndDate(): ?\DateTimeImmutable
    {
        return $this->warrantyEndDate;
    }

    public function setWarrantyEndDate(?\DateTimeImmutable $warrantyEndDate): static
    {
        $this->warrantyEndDate = $warrantyEndDate;

        return $this;
    }

    public function getReservationsStatus(): ReservationsStatus
    {
        return $this->reservationsStatus;
    }

    public function setReservationsStatus(ReservationsStatus $reservationsStatus): static
    {
        $this->reservationsStatus = $reservationsStatus;

        return $this;
    }

    public function getDocument(): ?ContractDocument
    {
        return $this->document;
    }

    public function setDocument(?ContractDocument $document): static
    {
        $this->document = $document;

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

    public function isFinal(): bool
    {
        return ReceiptType::Final === $this->receiptType;
    }

    public function isProvisional(): bool
    {
        return ReceiptType::Provisional === $this->receiptType;
    }

    public function isValidated(): bool
    {
        return null !== $this->validatedAt && null !== $this->validatedBy;
    }

    /** Validation explicite par une personne habilitée (§15.5). */
    public function validate(User $user): static
    {
        $this->validatedBy = $user;
        $this->validatedAt = new \DateTimeImmutable();

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

    /** @return Collection<int, ContractReservation> */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(ContractReservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setReceipt($this);
            $this->refreshReservationsStatus();
        }

        return $this;
    }

    public function removeReservation(ContractReservation $reservation): static
    {
        $this->reservations->removeElement($reservation);
        $this->refreshReservationsStatus();

        return $this;
    }

    public function getOpenReservationsCount(): int
    {
        $count = 0;
        foreach ($this->reservations as $reservation) {
            if ($reservation->isOpen()) {
                ++$count;
            }
        }

        return $count;
    }

    /** Recalcule l'état global à partir des réserves individuelles. */
    public function refreshReservationsStatus(): void
    {
        if (0 === $this->reservations->count()) {
            $this->reservationsStatus = ReservationsStatus::None;

            return;
        }

        $open = 0;
        $lifted = 0;
        foreach ($this->reservations as $reservation) {
            if ($reservation->isOpen()) {
                ++$open;
            } elseif (\App\Enum\ReservationStatus::Lifted === $reservation->getStatus()) {
                ++$lifted;
            }
        }

        if ($open > 0 && $lifted > 0) {
            $this->reservationsStatus = ReservationsStatus::PartiallyLifted;

            return;
        }

        $this->reservationsStatus = $open > 0 ? ReservationsStatus::Open : ReservationsStatus::Lifted;
    }

    public function __toString(): string
    {
        return $this->receiptType->label().' — '.($this->pvReference ?? 'PV non renseigné');
    }
}
