<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal d'audit (§5.2 Z) : conserve l'auteur, l'action, l'entité concernée,
 * l'horodatage, les valeurs avant / après modification lorsque nécessaire, et
 * le résultat de l'action.
 *
 * Une ligne est écrite pour toute opération sensible : validation, annulation,
 * clôture, réouverture, modification de droits, accès refusé (Test F).
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_occurred_at', columns: ['occurred_at'])]
class AuditLog
{
    use UuidIdTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $user = null;

    /** Nom de connexion conservé même si le compte est supprimé. */
    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    private ?string $username = null;

    /** Action réalisée : create, update, validate, cancel, close, reopen, deny… */
    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $action;

    /** Entité concernée (nom court : Project, Expense, Invoice…). */
    #[ORM\Column(type: Types::STRING, length: 80)]
    private string $entity;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $entityId = null;

    /** Module fonctionnel (M01 … M14) pour le filtrage du journal. */
    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $module = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $beforeData = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $afterData = null;

    /** Résultat de l'action : success, denied, failure. */
    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'success'])]
    private string $result = 'success';

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    public function __construct(string $action = '', string $entity = '')
    {
        $this->ensureId();
        $this->action = $action;
        $this->entity = $entity;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        $this->username ??= $user?->getUsername();

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(?string $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    /** @param array<string, mixed>|null $beforeData */
    public function setBeforeData(?array $beforeData): static
    {
        $this->beforeData = $beforeData;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    /** @param array<string, mixed>|null $afterData */
    public function setAfterData(?array $afterData): static
    {
        $this->afterData = $afterData;

        return $this;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function setResult(string $result): static
    {
        $this->result = $result;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }
}
