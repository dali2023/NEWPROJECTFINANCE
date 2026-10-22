<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\SoftDeletableTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ClientType;
use App\Repository\ClientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fiche client (§4.2).
 */
#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
#[ORM\UniqueConstraint(name: 'uniq_clients_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Ce code client est déjà utilisé.')]
class Client
{
    use UuidIdTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(type: Types::STRING, length: 30)]
    #[Assert\NotBlank(message: 'Le code client est obligatoire.')]
    #[Assert\Regex(pattern: '/^[A-Z0-9-]{3,30}$/', message: 'Code attendu : majuscules, chiffres et tirets (3 à 30 caractères).')]
    private string $code = '';

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'La raison sociale est obligatoire.')]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ClientType::class)]
    private ClientType $clientType = ClientType::Private;

    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    private ?string $taxIdentifier = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: Types::STRING, length: 80, nullable: true)]
    private ?string $country = 'Tunisie';

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Email(message: 'Adresse électronique invalide.')]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 40, nullable: true)]
    private ?string $phone = null;

    /** Contact principal (§4.2). */
    #[ORM\Column(type: Types::STRING, length: 160, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 30])]
    #[Assert\PositiveOrZero(message: 'Le délai de paiement ne peut pas être négatif.')]
    #[Assert\LessThanOrEqual(365, message: 'Le délai de paiement ne peut pas dépasser 365 jours.')]
    private int $paymentTermsDays = 30;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->ensureId();
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

    public function getClientType(): ClientType
    {
        return $this->clientType;
    }

    public function setClientType(ClientType $clientType): static
    {
        $this->clientType = $clientType;

        return $this;
    }

    public function getTaxIdentifier(): ?string
    {
        return $this->taxIdentifier;
    }

    public function setTaxIdentifier(?string $taxIdentifier): static
    {
        $this->taxIdentifier = $taxIdentifier;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): static
    {
        $this->contactName = $contactName;

        return $this;
    }

    public function getPaymentTermsDays(): int
    {
        return $this->paymentTermsDays;
    }

    public function setPaymentTermsDays(int $paymentTermsDays): static
    {
        $this->paymentTermsDays = $paymentTermsDays;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function __toString(): string
    {
        return $this->code.' — '.$this->name;
    }
}
