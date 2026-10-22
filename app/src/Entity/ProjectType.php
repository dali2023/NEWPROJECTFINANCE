<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Repository\ProjectTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Typologie des projets (§4.3) : commercialisation de licences, intégration ERP,
 * déploiement, développement spécifique, maintenance, formation, conseil, mixte.
 * La liste est modifiable par un administrateur.
 */
#[ORM\Entity(repositoryClass: ProjectTypeRepository::class)]
#[ORM\Table(name: 'project_types')]
#[ORM\UniqueConstraint(name: 'uniq_project_types_code', columns: ['code'])]
#[UniqueEntity(fields: ['code'], message: 'Ce code de type de projet est déjà utilisé.')]
#[ORM\HasLifecycleCallbacks]
class ProjectType
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 30)]
    #[Assert\NotBlank(message: 'Le code est obligatoire.')]
    #[Assert\Regex(pattern: '/^[A-Z0-9_]{2,30}$/', message: 'Code attendu : majuscules, chiffres et tirets bas.')]
    private string $code = '';

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    private string $label = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $sortOrder = 0;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
