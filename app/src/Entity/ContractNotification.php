<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ContractNotificationSeverity;
use App\Enum\ContractNotificationStatus;
use App\Enum\ContractNotificationType;
use App\Repository\ContractNotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Notification interne liée à une échéance contractuelle (§15.7).
 *
 * `uniqueKey` intègre le contrat, l'échéance, le type d'événement, la date
 * d'événement, le délai et le destinataire. Elle garantit qu'une même alerte
 * n'est jamais créée deux fois, y compris en cas de génération concurrente.
 */
#[ORM\Entity(repositoryClass: ContractNotificationRepository::class)]
#[ORM\Table(name: 'contract_notifications')]
#[ORM\UniqueConstraint(name: 'uniq_contract_notifications_key', columns: ['unique_key'])]
#[ORM\Index(name: 'idx_contract_notifications_status', columns: ['status'])]
#[ORM\Index(name: 'idx_contract_notifications_type', columns: ['notification_type'])]
#[ORM\Index(name: 'idx_contract_notifications_trigger', columns: ['trigger_date'])]
#[ORM\Index(name: 'idx_contract_notifications_recipient', columns: ['recipient_user_id'])]
#[ORM\HasLifecycleCallbacks]
class ContractNotification
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    #[ORM\ManyToOne(targetEntity: ContractMilestone::class)]
    #[ORM\JoinColumn(name: 'milestone_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?ContractMilestone $milestone = null;

    #[ORM\ManyToOne(targetEntity: ContractReceipt::class)]
    #[ORM\JoinColumn(name: 'receipt_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?ContractReceipt $receipt = null;

    #[ORM\ManyToOne(targetEntity: ContractReservation::class)]
    #[ORM\JoinColumn(name: 'reservation_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?ContractReservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: RetentionGuarantee::class)]
    #[ORM\JoinColumn(name: 'retention_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?RetentionGuarantee $retention = null;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: ContractNotificationType::class)]
    private ContractNotificationType $notificationType;

    /** Date de l'événement surveillé (expiration, échéance, fin de garantie…). */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $eventDate;

    /** Date à laquelle l'alerte devient due (événement − délai). */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $triggerDate;

    /** Délai de paramétrage ; une valeur négative signifie « après échéance ». */
    #[ORM\Column(type: Types::INTEGER)]
    private int $alertDelayDays;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ContractNotificationSeverity::class)]
    private ContractNotificationSeverity $severity;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $recipient = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ContractNotificationStatus::class)]
    private ContractNotificationStatus $status = ContractNotificationStatus::Pending;

    #[ORM\Column(type: Types::STRING, length: 250)]
    #[Assert\NotBlank(message: 'La clé d’unicité est obligatoire.')]
    private string $uniqueKey = '';

    #[ORM\Column(type: Types::STRING, length: 250)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resolutionNote = null;

    public function __construct()
    {
        $this->ensureId();
        $this->sentAt = new \DateTimeImmutable();
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

    public function getMilestone(): ?ContractMilestone
    {
        return $this->milestone;
    }

    public function setMilestone(?ContractMilestone $milestone): static
    {
        $this->milestone = $milestone;

        return $this;
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

    public function getReservation(): ?ContractReservation
    {
        return $this->reservation;
    }

    public function setReservation(?ContractReservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getRetention(): ?RetentionGuarantee
    {
        return $this->retention;
    }

    public function setRetention(?RetentionGuarantee $retention): static
    {
        $this->retention = $retention;

        return $this;
    }

    public function getNotificationType(): ContractNotificationType
    {
        return $this->notificationType;
    }

    public function setNotificationType(ContractNotificationType $notificationType): static
    {
        $this->notificationType = $notificationType;

        return $this;
    }

    public function getEventDate(): \DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeImmutable $eventDate): static
    {
        $this->eventDate = $eventDate;

        return $this;
    }

    public function getTriggerDate(): \DateTimeImmutable
    {
        return $this->triggerDate;
    }

    public function setTriggerDate(\DateTimeImmutable $triggerDate): static
    {
        $this->triggerDate = $triggerDate;

        return $this;
    }

    public function getAlertDelayDays(): int
    {
        return $this->alertDelayDays;
    }

    public function setAlertDelayDays(int $alertDelayDays): static
    {
        $this->alertDelayDays = $alertDelayDays;

        return $this;
    }

    public function getSeverity(): ContractNotificationSeverity
    {
        return $this->severity;
    }

    public function setSeverity(ContractNotificationSeverity $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getStatus(): ContractNotificationStatus
    {
        return $this->status;
    }

    public function setStatus(ContractNotificationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUniqueKey(): string
    {
        return $this->uniqueKey;
    }

    public function setUniqueKey(string $uniqueKey): static
    {
        $this->uniqueKey = $uniqueKey;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getResolutionNote(): ?string
    {
        return $this->resolutionNote;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function acknowledge(?User $assignee = null): void
    {
        if (null !== $assignee) {
            $this->recipient = $assignee;
        }
        $this->status = ContractNotificationStatus::InProgress;
        $this->acknowledgedAt = new \DateTimeImmutable();
    }

    public function resolve(string $note): void
    {
        $this->status = ContractNotificationStatus::Resolved;
        $this->resolvedAt = new \DateTimeImmutable();
        $this->resolutionNote = $note;
    }

    public function dismiss(string $reason): void
    {
        $this->status = ContractNotificationStatus::Dismissed;
        $this->resolvedAt = new \DateTimeImmutable();
        $this->resolutionNote = $reason;
    }

    /** Libellé du délai, par exemple « J-30 » ou « Après échéance ». */
    public function getDelayLabel(): string
    {
        if ($this->alertDelayDays < 0) {
            return 'Après échéance';
        }

        if (0 === $this->alertDelayDays) {
            return 'À échéance';
        }

        return 'J-'.$this->alertDelayDays;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
