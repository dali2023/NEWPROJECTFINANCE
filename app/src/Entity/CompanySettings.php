<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Repository\CompanySettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paramètres de la société (identité, coordonnées, logo).
 *
 * Une seule fiche est utilisée par l'application : elle alimente le bandeau,
 * les impressions et les emails de rappel. Les modifications sont réservées
 * aux administrateurs et journalisées.
 */
#[ORM\Entity(repositoryClass: CompanySettingsRepository::class)]
#[ORM\Table(name: 'company_settings')]
#[ORM\HasLifecycleCallbacks]
class CompanySettings
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 180)]
    #[Assert\NotBlank(message: 'La raison sociale est obligatoire.')]
    #[Assert\Length(max: 180)]
    private string $legalName = 'ONE ERP SARL';

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $tradeName = null;

    /** Matricule fiscal (identifiant fiscal de l'entreprise). */
    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $taxId = null;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Email(message: 'Adresse électronique invalide.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $phoneSecondary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $postalCode = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $country = 'Tunisie';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Url(message: 'Adresse web invalide.', requireTld: true)]
    private ?string $website = null;

    /** Chemin relatif dans le stockage des documents (hors répertoire public). */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $logoMimeType = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $logoOriginalName = null;

    public function __construct()
    {
        $this->ensureId();
    }

    public function getLegalName(): string
    {
        return $this->legalName;
    }

    public function setLegalName(string $legalName): static
    {
        $this->legalName = trim($legalName);

        return $this;
    }

    public function getTradeName(): ?string
    {
        return $this->tradeName;
    }

    public function setTradeName(?string $tradeName): static
    {
        $tradeName = null !== $tradeName ? trim($tradeName) : null;
        $this->tradeName = '' === $tradeName ? null : $tradeName;

        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): static
    {
        $taxId = null !== $taxId ? trim($taxId) : null;
        $this->taxId = '' === $taxId ? null : $taxId;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = null !== $email ? trim($email) : null;
        $this->email = '' === $email ? null : $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $phone = null !== $phone ? trim($phone) : null;
        $this->phone = '' === $phone ? null : $phone;

        return $this;
    }

    public function getPhoneSecondary(): ?string
    {
        return $this->phoneSecondary;
    }

    public function setPhoneSecondary(?string $phoneSecondary): static
    {
        $phoneSecondary = null !== $phoneSecondary ? trim($phoneSecondary) : null;
        $this->phoneSecondary = '' === $phoneSecondary ? null : $phoneSecondary;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $address = null !== $address ? trim($address) : null;
        $this->address = '' === $address ? null : $address;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $postalCode = null !== $postalCode ? trim($postalCode) : null;
        $this->postalCode = '' === $postalCode ? null : $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $city = null !== $city ? trim($city) : null;
        $this->city = '' === $city ? null : $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $country = null !== $country ? trim($country) : null;
        $this->country = '' === $country ? null : $country;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $website = null !== $website ? trim($website) : null;
        $this->website = '' === $website ? null : $website;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    public function getLogoMimeType(): ?string
    {
        return $this->logoMimeType;
    }

    public function setLogoMimeType(?string $logoMimeType): static
    {
        $this->logoMimeType = $logoMimeType;

        return $this;
    }

    public function getLogoOriginalName(): ?string
    {
        return $this->logoOriginalName;
    }

    public function setLogoOriginalName(?string $logoOriginalName): static
    {
        $this->logoOriginalName = $logoOriginalName;

        return $this;
    }

    public function hasLogo(): bool
    {
        return null !== $this->logoPath && '' !== $this->logoPath;
    }

    /** Nom affiché dans l'application (nom commercial, sinon raison sociale). */
    public function getDisplayName(): string
    {
        return $this->tradeName ?? $this->legalName;
    }

    /** Initiales utilisées si aucun logo n'est enregistré. */
    public function getInitials(): string
    {
        $source = trim($this->getDisplayName());
        if ('' === $source) {
            return 'ONE';
        }

        $words = preg_split('/\s+/u', $source) ?: [];
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_substr($word, 0, 1);
        }

        return mb_strtoupper($initials) ?: 'ONE';
    }

    /** Adresse postale sur une seule ligne, pour l'affichage et les emails. */
    public function getFormattedAddress(): string
    {
        $cityLine = trim(($this->postalCode ?? '').' '.($this->city ?? ''));
        $parts = array_filter(
            [$this->address, $cityLine, $this->country],
            static fn (?string $value): bool => null !== $value && '' !== trim($value),
        );

        return implode(', ', $parts);
    }

    /** @return list<string> */
    public function getPhones(): array
    {
        return array_values(array_filter(
            [$this->phone, $this->phoneSecondary],
            static fn (?string $value): bool => null !== $value && '' !== trim($value),
        ));
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}
