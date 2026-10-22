<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Repository\ContractMilestoneHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique des reports d'échéance (§15.7, REC-032).
 *
 * La date antérieure reste conservée ; la date reportée devient la date active
 * portée par l'échéance.
 */
#[ORM\Entity(repositoryClass: ContractMilestoneHistoryRepository::class)]
#[ORM\Table(name: 'contract_milestone_histories')]
#[ORM\Index(name: 'idx_milestone_history_milestone', columns: ['milestone_id'])]
#[ORM\HasLifecycleCallbacks]
class ContractMilestoneHistory
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: ContractMilestone::class, inversedBy: 'history')]
    #[ORM\JoinColumn(name: 'milestone_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ContractMilestone $milestone = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $oldPlannedDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $newPlannedDate = null;

    #[ORM\Column(type: Types::STRING, length: 30, options: ['default' => 'postponement'])]
    private string $changeType = 'postponement';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'changed_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    public function __construct()
    {
        $this->ensureId();
        $this->changedAt = new \DateTimeImmutable();
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

    public function getOldPlannedDate(): ?\DateTimeImmutable
    {
        return $this->oldPlannedDate;
    }

    public function setOldPlannedDate(?\DateTimeImmutable $oldPlannedDate): static
    {
        $this->oldPlannedDate = $oldPlannedDate;

        return $this;
    }

    public function getNewPlannedDate(): ?\DateTimeImmutable
    {
        return $this->newPlannedDate;
    }

    public function setNewPlannedDate(?\DateTimeImmutable $newPlannedDate): static
    {
        $this->newPlannedDate = $newPlannedDate;

        return $this;
    }

    public function getChangeType(): string
    {
        return $this->changeType;
    }

    public function setChangeType(string $changeType): static
    {
        $this->changeType = $changeType;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): static
    {
        $this->changedBy = $changedBy;

        return $this;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
