<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\SoftDeletableTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ProjectStatus;
use App\Repository\ProjectRepository;
use App\Service\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fiche financière unique d'un projet (§4.3, §5.2 F).
 *
 * Les montants sont stockés en NUMERIC(18,3) et manipulés comme chaînes
 * décimales : jamais de flottant binaire (§5.1).
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\UniqueConstraint(name: 'uniq_projects_code', columns: ['code'])]
#[ORM\Index(name: 'idx_projects_status', columns: ['status'])]
#[ORM\Index(name: 'idx_projects_client', columns: ['client_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Cette référence de projet est déjà utilisée.')]
class Project
{
    use UuidIdTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(type: Types::STRING, length: 30)]
    #[Assert\NotBlank(message: 'La référence du projet est obligatoire.')]
    private string $code = '';

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'L’intitulé du projet est obligatoire.')]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Le client est obligatoire.')]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: ProjectType::class)]
    #[ORM\JoinColumn(name: 'project_type_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Le type de projet est obligatoire.')]
    private ?ProjectType $projectType = null;

    /** Responsable du projet (§4.3). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'manager_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $manager = null;

    /** Équipe projet : les membres accèdent au projet via project.view_own (§3.6). */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'project_members')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $members;

    /** Numéro de marché ou de contrat. */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $contractReference = null;

    /** Référence de l'appel d'offres. */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $tenderReference = null;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: ProjectStatus::class)]
    private ProjectStatus $status = ProjectStatus::Draft;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $signedOn = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $plannedDeliveryDate = null;

    #[ORM\Column(type: Types::STRING, length: 3, options: ['default' => 'TND'])]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Devise invalide : code ISO à trois lettres attendu.')]
    private string $currencyCode = 'TND';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le montant contractuel ne peut pas être négatif.')]
    private string $contractAmountHt = '0.000';

    /** Taux de TVA applicable (19 % par défaut en Tunisie). */
    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 4, options: ['default' => '0.0000'])]
    #[Assert\PositiveOrZero(message: 'Le taux de TVA ne peut pas être négatif.')]
    #[Assert\LessThanOrEqual(100, message: 'Le taux de TVA ne peut pas dépasser 100 %.')]
    private string $vatRate = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    private string $vatAmount = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    private string $contractAmountTtc = '0.000';

    /** Budget prévisionnel des charges (§4.3). */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le budget ne peut pas être négatif.')]
    private string $budgetAmount = '0.000';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $paymentTerms = null;

    /** Probabilité de conclusion, lorsque le projet est une opportunité (§4.3). */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'La probabilité doit être comprise entre 0 et 100 %.')]
    private ?int $probability = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'closed_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, ProjectMilestone> */
    #[ORM\OneToMany(targetEntity: ProjectMilestone::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $milestones;

    public function __construct()
    {
        $this->ensureId();
        $this->milestones = new ArrayCollection();
        $this->members = new ArrayCollection();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

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

    public function getProjectType(): ?ProjectType
    {
        return $this->projectType;
    }

    public function setProjectType(?ProjectType $projectType): static
    {
        $this->projectType = $projectType;

        return $this;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(?User $manager): static
    {
        $this->manager = $manager;
        if (null !== $manager) {
            $this->addMember($manager);
        }

        return $this;
    }

    /** @return Collection<int, User> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $user): static
    {
        if (!$this->members->contains($user)) {
            $this->members->add($user);
        }

        return $this;
    }

    public function removeMember(User $user): static
    {
        $this->members->removeElement($user);

        return $this;
    }

    public function hasMember(?User $user): bool
    {
        if (null === $user) {
            return false;
        }

        return $this->members->contains($user);
    }

    public function getContractReference(): ?string
    {
        return $this->contractReference;
    }

    public function setContractReference(?string $contractReference): static
    {
        $this->contractReference = $contractReference;

        return $this;
    }

    public function getTenderReference(): ?string
    {
        return $this->tenderReference;
    }

    public function setTenderReference(?string $tenderReference): static
    {
        $this->tenderReference = $tenderReference;

        return $this;
    }

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    public function setStatus(ProjectStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** Le projet compte-t-il comme contrat signé ? (§4.3, §8.1) */
    public function isContractual(): bool
    {
        return $this->status->isContractual();
    }

    public function getSignedOn(): ?\DateTimeImmutable
    {
        return $this->signedOn;
    }

    public function setSignedOn(?\DateTimeImmutable $signedOn): static
    {
        $this->signedOn = $signedOn;

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

    public function getPlannedDeliveryDate(): ?\DateTimeImmutable
    {
        return $this->plannedDeliveryDate;
    }

    public function setPlannedDeliveryDate(?\DateTimeImmutable $plannedDeliveryDate): static
    {
        $this->plannedDeliveryDate = $plannedDeliveryDate;

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

    public function getContractAmountHt(): string
    {
        return $this->contractAmountHt;
    }

    public function setContractAmountHt(string $contractAmountHt): static
    {
        $this->contractAmountHt = $contractAmountHt;
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

    public function getContractAmountTtc(): string
    {
        return $this->contractAmountTtc;
    }

    /**
     * §6.1 : Montant TTC = Montant HT + TVA, avec TVA = HT × taux / 100.
     * Calcul exact en décimal (bcmath), arrondi commercial au millime.
     */
    public function recomputeTotals(): void
    {
        $this->vatAmount = Money::percentOf($this->contractAmountHt, $this->vatRate);
        $this->contractAmountTtc = Money::add($this->contractAmountHt, $this->vatAmount);
    }

    public function getBudgetAmount(): string
    {
        return $this->budgetAmount;
    }

    public function setBudgetAmount(string $budgetAmount): static
    {
        $this->budgetAmount = $budgetAmount;

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

    public function getProbability(): ?int
    {
        return $this->probability;
    }

    public function setProbability(?int $probability): static
    {
        $this->probability = $probability;

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
        $this->status = ProjectStatus::Closed;
        $this->closedAt = new \DateTimeImmutable();
        $this->closedBy = $by;

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

    /** @return Collection<int, ProjectMilestone> */
    public function getMilestones(): Collection
    {
        return $this->milestones;
    }

    public function addMilestone(ProjectMilestone $milestone): static
    {
        if (!$this->milestones->contains($milestone)) {
            $this->milestones->add($milestone);
            $milestone->setProject($this);
        }

        return $this;
    }

    public function removeMilestone(ProjectMilestone $milestone): static
    {
        $this->milestones->removeElement($milestone);

        return $this;
    }

    /** Montant de l'échéancier contractuel (somme des jalons). */
    public function getMilestonesTotalHt(): string
    {
        return Money::sum(array_map(
            static fn (ProjectMilestone $milestone): string => $milestone->getAmountHt(),
            $this->milestones->toArray(),
        ));
    }

    public function __toString(): string
    {
        return $this->code.' — '.$this->name;
    }
}
