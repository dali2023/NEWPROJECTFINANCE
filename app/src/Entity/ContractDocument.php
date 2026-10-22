<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\ContractDocumentType;
use App\Repository\ContractDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Document contractuel conservé hors du répertoire public (§15.2, §15.5).
 *
 * Le chemin stocké est relatif au répertoire de justificatifs : il n'est jamais
 * exposé directement par une URL publique.
 */
#[ORM\Entity(repositoryClass: ContractDocumentRepository::class)]
#[ORM\Table(name: 'contract_documents')]
#[ORM\Index(name: 'idx_contract_documents_contract', columns: ['contract_id'])]
#[ORM\HasLifecycleCallbacks]
class ContractDocument
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'Le libellé du document est obligatoire.')]
    private string $label = '';

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ContractDocumentType::class)]
    private ContractDocumentType $documentType = ContractDocumentType::Other;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $filePath = '';

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $fileSize = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);

        return $this;
    }

    public function getDocumentType(): ContractDocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(ContractDocumentType $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

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

    public function __toString(): string
    {
        return $this->label;
    }
}
