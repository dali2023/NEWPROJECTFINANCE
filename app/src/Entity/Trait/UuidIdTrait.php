<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Identifiant interne unique (UUID v7, ordonné dans le temps) — §5.1.
 */
trait UuidIdTrait
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private ?Uuid $id = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /** Identifiant généré à la demande (avant persistance). */
    public function ensureId(): Uuid
    {
        return $this->id ??= Uuid::v7();
    }

    public function getIdString(): string
    {
        return $this->id?->toRfc4122() ?? '';
    }
}
