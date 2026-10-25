<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\SoftDeletableTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ProspectStatus;
use App\Repository\ProspectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prospect commercial (avant conversion en client).
 *
 * Le prospect dispose de son propre cycle de vie : il ne pollue pas la table
 * des clients, réservée aux tiers déjà qualifiés. La conversion est tracée par
 * les liens vers le client, le projet et le contrat brouillon créés.
 */
#[ORM\Entity(repositoryClass: ProspectRepository::class)]
#[ORM\Table(name: 'prospects')]
#[ORM\UniqueConstraint(name: 'uniq_prospects_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_prospects_status', columns: ['status'])]
#[ORM\Index(name: 'idx_prospects_email', columns: ['email'])]
#[ORM\Index(name: 'idx_prospects_name_city', columns: ['name', 'city'])]
#[ORM\Index(name: 'idx_prospects_owner', columns: ['owner_user_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence de prospect est déjà utilisée.')]
class Prospect
{
    use UuidIdTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(type: Types::STRING, length: 30)]
    #[Assert\NotBlank(message: 'La référence du prospect est obligatoire.')]
    private string $reference = '';

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'La raison sociale est obligatoire.')]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 160, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Email(message: 'Adresse électronique invalide.')]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: Types::STRING, length: 80, nullable: true)]
    private ?string $country = 'Tunisie';

    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    private ?string $taxIdentifier = null;

    /** Origine du prospect : salon, recommandation, site web, appel d'offres… */
    #[ORM\Column(type: Types::STRING, length: 80, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: ProspectStatus::class)]
    private ProspectStatus $status = ProspectStatus::New;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero(message: 'Le montant estimé ne peut pas être négatif.')]
    private string $estimatedAmountHt = '0.000';

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'La probabilité doit être comprise entre 0 et 100 %.')]
    private ?int $probability = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expectedCloseDate = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $nextAction = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextActionDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $convertedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'converted_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $convertedBy = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'converted_client_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Client $convertedClient = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'converted_project_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Project $convertedProject = null;

    #[ORM\ManyToOne(targetEntity: Contract::class)]
    #[ORM\JoinColumn(name: 'converted_contract_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Contract $convertedContract = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): static
    {
        $contactName = null === $contactName ? null : trim($contactName);
        $this->contactName = '' === $contactName ? null : $contactName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = null === $email ? null : trim($email);
        $this->email = '' === $email ? null : mb_strtolower($email);

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $phone = null === $phone ? null : trim($phone);
        $this->phone = '' === $phone ? null : $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $address = null === $address ? null : trim($address);
        $this->address = '' === $address ? null : $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $city = null === $city ? null : trim($city);
        $this->city = '' === $city ? null : $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $postalCode = null === $postalCode ? null : trim($postalCode);
        $this->postalCode = '' === $postalCode ? null : $postalCode;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $country = null === $country ? null : trim($country);
        $this->country = '' === $country ? null : $country;

        return $this;
    }

    public function getTaxIdentifier(): ?string
    {
        return $this->taxIdentifier;
    }

    public function setTaxIdentifier(?string $taxIdentifier): static
    {
        $taxIdentifier = null === $taxIdentifier ? null : trim($taxIdentifier);
        $this->taxIdentifier = '' === $taxIdentifier ? null : $taxIdentifier;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $source = null === $source ? null : trim($source);
        $this->source = '' === $source ? null : $source;

        return $this;
    }

    public function getStatus(): ProspectStatus
    {
        return $this->status;
    }

    public function setStatus(ProspectStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getEstimatedAmountHt(): string
    {
        return $this->estimatedAmountHt;
    }

    public function setEstimatedAmountHt(string $estimatedAmountHt): static
    {
        $this->estimatedAmountHt = $estimatedAmountHt;

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

    public function getExpectedCloseDate(): ?\DateTimeImmutable
    {
        return $this->expectedCloseDate;
    }

    public function setExpectedCloseDate(?\DateTimeImmutable $expectedCloseDate): static
    {
        $this->expectedCloseDate = $expectedCloseDate;

        return $this;
    }

    public function getNextAction(): ?string
    {
        return $this->nextAction;
    }

    public function setNextAction(?string $nextAction): static
    {
        $nextAction = null === $nextAction ? null : trim($nextAction);
        $this->nextAction = '' === $nextAction ? null : $nextAction;

        return $this;
    }

    public function getNextActionDate(): ?\DateTimeImmutable
    {
        return $this->nextActionDate;
    }

    public function setNextActionDate(?\DateTimeImmutable $nextActionDate): static
    {
        $this->nextActionDate = $nextActionDate;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $notes = null === $notes ? null : trim($notes);
        $this->notes = '' === $notes ? null : $notes;

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

    public function getConvertedAt(): ?\DateTimeImmutable
    {
        return $this->convertedAt;
    }

    public function getConvertedBy(): ?User
    {
        return $this->convertedBy;
    }

    public function getConvertedClient(): ?Client
    {
        return $this->convertedClient;
    }

    public function getConvertedProject(): ?Project
    {
        return $this->convertedProject;
    }

    public function getConvertedContract(): ?Contract
    {
        return $this->convertedContract;
    }

    public function isConverted(): bool
    {
        return null !== $this->convertedAt;
    }

    public function canConvert(): bool
    {
        return !$this->isConverted() && !$this->status->isLost();
    }

    public function markConverted(
        Client $client,
        Project $project,
        Contract $contract,
        User $actor,
        ?\DateTimeImmutable $at = null,
    ): static {
        $this->status = ProspectStatus::Converted;
        $this->convertedAt = $at ?? new \DateTimeImmutable();
        $this->convertedBy = $actor;
        $this->convertedClient = $client;
        $this->convertedProject = $project;
        $this->convertedContract = $contract;

        return $this;
    }

    public function __toString(): string
    {
        return $this->reference.' — '.$this->name;
    }
}
