<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\RenewalDecisionStatus;
use App\Repository\ContractRenewalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Décision et suivi d'un renouvellement (§15.4).
 *
 * `contract` désigne le contrat issu du renouvellement lorsqu'il existe, et
 * `previousContract` le contrat arrivé à échéance. Un renouvellement n'est
 * jamais déduit de l'absence de résiliation.
 */
#[ORM\Entity(repositoryClass: ContractRenewalRepository::class)]
#[ORM\Table(name: 'contract_renewals')]
#[ORM\Index(name: 'idx_contract_renewals_contract', columns: ['contract_id'])]
#[ORM\Index(name: 'idx_contract_renewals_previous', columns: ['previous_contract_id'])]
#[ORM\HasLifecycleCallbacks]
class ContractRenewal
{
    use UuidIdTrait;
    use TimestampableTrait;

    /** Contrat renouvelé (nouveau contrat) ou contrat concerné si non renouvelé. */
    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'renewals')]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    #[ORM\ManyToOne(targetEntity: Contract::class)]
    #[ORM\JoinColumn(name: 'previous_contract_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Contract $previousContract = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $renewalDeadline = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decisionDate = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: RenewalDecisionStatus::class)]
    private RenewalDecisionStatus $decisionStatus = RenewalDecisionStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Contract::class)]
    #[ORM\JoinColumn(name: 'new_contract_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Contract $newContract = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le montant du renouvellement ne peut pas être négatif.')]
    private string $renewalAmountHt = '0.000';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'responsible_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $responsible = null;

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

    public function getPreviousContract(): ?Contract
    {
        return $this->previousContract;
    }

    public function setPreviousContract(?Contract $previousContract): static
    {
        $this->previousContract = $previousContract;

        return $this;
    }

    public function getRenewalDeadline(): ?\DateTimeImmutable
    {
        return $this->renewalDeadline;
    }

    public function setRenewalDeadline(?\DateTimeImmutable $renewalDeadline): static
    {
        $this->renewalDeadline = $renewalDeadline;

        return $this;
    }

    public function getDecisionDate(): ?\DateTimeImmutable
    {
        return $this->decisionDate;
    }

    public function setDecisionDate(?\DateTimeImmutable $decisionDate): static
    {
        $this->decisionDate = $decisionDate;

        return $this;
    }

    public function getDecisionStatus(): RenewalDecisionStatus
    {
        return $this->decisionStatus;
    }

    public function setDecisionStatus(RenewalDecisionStatus $decisionStatus): static
    {
        $this->decisionStatus = $decisionStatus;

        return $this;
    }

    public function getNewContract(): ?Contract
    {
        return $this->newContract;
    }

    public function setNewContract(?Contract $newContract): static
    {
        $this->newContract = $newContract;

        return $this;
    }

    public function getRenewalAmountHt(): string
    {
        return $this->renewalAmountHt;
    }

    public function setRenewalAmountHt(string $renewalAmountHt): static
    {
        $this->renewalAmountHt = $renewalAmountHt;

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
}
