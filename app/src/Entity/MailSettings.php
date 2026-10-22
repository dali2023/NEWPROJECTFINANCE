<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\MailEncryption;
use App\Repository\MailSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paramètres d'envoi des emails (SMTP).
 *
 * Le mot de passe n'est jamais stocké en clair : il est chiffré par
 * App\Service\Settings\SecretCipher avant persistance. Le formulaire ne
 * réaffiche jamais la valeur existante.
 */
#[ORM\Entity(repositoryClass: MailSettingsRepository::class)]
#[ORM\Table(name: 'mail_settings')]
#[ORM\HasLifecycleCallbacks]
class MailSettings
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isEnabled = false;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le serveur SMTP est obligatoire.')]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?$/',
        message: 'Nom d’hôte invalide (sans http://, sans port).',
    )]
    private string $host = '';

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1, max: 65535, notInRangeMessage: 'Le port doit être compris entre 1 et 65535.')]
    private int $port = 587;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: MailEncryption::class)]
    private MailEncryption $encryption = MailEncryption::StartTls;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $username = null;

    /** Mot de passe SMTP chiffré (AES-256-GCM). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $passwordEncrypted = null;

    #[ORM\Column(type: Types::STRING, length: 180)]
    #[Assert\NotBlank(message: "L'adresse d'expédition est obligatoire.")]
    #[Assert\Email(message: "L'adresse d'expédition est invalide.")]
    private string $senderEmail = '';

    #[ORM\Column(type: Types::STRING, length: 180)]
    #[Assert\NotBlank(message: "Le nom de l'expéditeur est obligatoire.")]
    #[Assert\Length(max: 180)]
    private string $senderName = '';

    public function __construct()
    {
        $this->ensureId();
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): static
    {
        $this->host = trim($host);

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getEncryption(): MailEncryption
    {
        return $this->encryption;
    }

    public function setEncryption(MailEncryption $encryption): static
    {
        $this->encryption = $encryption;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $username = null !== $username ? trim($username) : null;
        $this->username = '' === $username ? null : $username;

        return $this;
    }

    public function getPasswordEncrypted(): ?string
    {
        return $this->passwordEncrypted;
    }

    public function setPasswordEncrypted(?string $passwordEncrypted): static
    {
        $this->passwordEncrypted = $passwordEncrypted;

        return $this;
    }

    public function hasPassword(): bool
    {
        return null !== $this->passwordEncrypted && '' !== $this->passwordEncrypted;
    }

    public function getSenderEmail(): string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(string $senderEmail): static
    {
        $this->senderEmail = trim($senderEmail);

        return $this;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): static
    {
        $this->senderName = trim($senderName);

        return $this;
    }

    /** Les paramètres minimaux sont-ils renseignés pour tenter un envoi ? */
    public function isConfigured(): bool
    {
        return '' !== trim($this->host)
            && $this->port > 0
            && false !== filter_var($this->senderEmail, \FILTER_VALIDATE_EMAIL);
    }

    public function getSenderLabel(): string
    {
        if ('' === trim($this->senderName)) {
            return $this->senderEmail;
        }

        return sprintf('%s <%s>', $this->senderName, $this->senderEmail);
    }

    public function __toString(): string
    {
        return '' !== $this->host ? $this->host.':'.$this->port : 'SMTP non configuré';
    }
}
