<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ReservationStatus;
use App\Repository\ContractReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réserve constatée lors d'une réception (§15.5).
 *
 * Une réserve non levée reste visible dans les alertes jusqu'à sa levée ou son
 * annulation motivée.
 */
#[ORM\Entity(repositoryClass: ContractReservationRepository::class)]
#[ORM\Table(name: 'contract_reservations')]
#[ORM\Index(name: 'idx_contract_reservations_receipt', columns: ['contract_receipt_id'])]
#[ORM\Index(name: 'idx_contract_reservations_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class ContractReservation
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: ContractReceipt::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'contract_receipt_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ContractReceipt $receipt = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description de la réserve est obligatoire.')]
    private string $description = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateIdentified = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $plannedResolutionDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualResolutionDate = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ReservationStatus::class)]
    private ReservationStatus $status = ReservationStatus::Open;

    #[ORM\ManyToOne(targetEntity: ContractDocument::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ContractDocument $document = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'responsible_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $responsible = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->ensureId();
        $this->dateIdentified = new \DateTimeImmutable('today');
    }

    public function getReceipt(): ?ContractReceipt
    {
        return $this->receipt;
    }

    public function setReceipt(?ContractReceipt $receipt): static
    {
        $this->receipt = $receipt;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = trim($description);

        return $this;
    }

    public function getDateIdentified(): ?\DateTimeImmutable
    {
        return $this->dateIdentified;
    }

    public function setDateIdentified(?\DateTimeImmutable $dateIdentified): static
    {
        $this->dateIdentified = $dateIdentified;

        return $this;
    }

    public function getPlannedResolutionDate(): ?\DateTimeImmutable
    {
        return $this->plannedResolutionDate;
    }

    public function setPlannedResolutionDate(?\DateTimeImmutable $plannedResolutionDate): static
    {
        $this->plannedResolutionDate = $plannedResolutionDate;

        return $this;
    }

    public function getActualResolutionDate(): ?\DateTimeImmutable
    {
        return $this->actualResolutionDate;
    }

    public function setActualResolutionDate(?\DateTimeImmutable $actualResolutionDate): static
    {
        $this->actualResolutionDate = $actualResolutionDate;

        return $this;
    }

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function setStatus(ReservationStatus $status): static
    {
        $this->status = $status;

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

    public function isOpen(): bool
    {
        return ReservationStatus::Open === $this->status;
    }

    /** Lève la réserve et met à jour l'état global de la réception. */
    public function lift(?\DateTimeImmutable $date = null): void
    {
        $this->status = ReservationStatus::Lifted;
        $this->actualResolutionDate = $date ?? new \DateTimeImmutable('today');
        $this->receipt?->refreshReservationsStatus();
    }

    public function __toString(): string
    {
        return $this->description;
    }
}
