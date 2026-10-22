<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compte utilisateur (§5.2 A). L'identifiant de connexion et l'adresse
 * électronique sont uniques, la casse étant ignorée.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_username', columns: ['username'])]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['username'], message: 'Ce nom de connexion est déjà utilisé.')]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse électronique est déjà utilisée.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 60)]
    #[Assert\NotBlank(message: 'Le nom de connexion est obligatoire.')]
    #[Assert\Length(min: 3, max: 60)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9._-]+$/', message: 'Seuls les lettres, chiffres, points, tirets et tirets bas sont autorisés.')]
    private string $username = '';

    #[ORM\Column(type: Types::STRING, length: 180)]
    #[Assert\NotBlank(message: 'L’adresse électronique est obligatoire.')]
    #[Assert\Email(message: 'Adresse électronique invalide.')]
    private string $email = '';

    #[ORM\Column(type: Types::STRING, length: 160)]
    #[Assert\NotBlank(message: 'Le nom complet est obligatoire.')]
    #[Assert\Length(max: 160)]
    private string $fullName = '';

    /** Empreinte du mot de passe (§9.2) — jamais le mot de passe en clair. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_roles')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $roles;

    /** Rôles applicatifs mis en cache pour Security. @var list<string> */
    private array $cachedRoles = [];

    public function __construct()
    {
        $this->ensureId();
        $this->roles = new ArrayCollection();
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        // La comparaison des doublons est insensible à la casse (§5.4).
        $this->username = trim($username);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = trim($email);

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = trim($fullName);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

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

    /**
     * Un compte désactivé ne peut plus se connecter (§3.1).
     */
    public function isAccountUsable(): bool
    {
        return $this->isActive && null !== $this->passwordHash;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function touchLastLogin(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        if ([] === $this->cachedRoles) {
            $codes = ['ROLE_USER'];
            foreach ($this->roles as $role) {
                $codes[] = $role->getCode();
            }
            $this->cachedRoles = array_values(array_unique($codes));
        }

        return $this->cachedRoles;
    }

    /** @return Collection<int, Role> */
    public function getAssignedRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
            $this->cachedRoles = [];
        }

        return $this;
    }

    public function removeRole(Role $role): static
    {
        $this->roles->removeElement($role);
        $this->cachedRoles = [];

        return $this;
    }

    public function hasRoleCode(string $code): bool
    {
        return \in_array($code, $this->getRoles(), true);
    }

    /** Codes des permissions effectives, agrégés depuis les rôles. */
    public function getPermissionCodes(): array
    {
        if ($this->hasRoleCode('ROLE_ADMIN')) {
            return ['*'];
        }

        $codes = [];
        foreach ($this->roles as $role) {
            foreach ($role->getPermissions() as $permission) {
                $codes[$permission->getCode()] = true;
            }
        }

        return array_keys($codes);
    }

    public function eraseCredentials(): void
    {
        // Aucune donnée sensible temporaire à effacer.
    }

    public function __toString(): string
    {
        return $this->fullName !== '' ? $this->fullName : $this->username;
    }
}
