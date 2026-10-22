<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Repository\InvoiceLineRepository;
use App\Service\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Ligne de facture (M04). */
#[ORM\Entity(repositoryClass: InvoiceLineRepository::class)]
#[ORM\Table(name: 'invoice_lines')]
#[ORM\Index(name: 'idx_invoice_lines_invoice', columns: ['invoice_id'])]
#[ORM\HasLifecycleCallbacks]
class InvoiceLine
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'invoice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne(targetEntity: ContractMilestone::class)]
    #[ORM\JoinColumn(name: 'contract_milestone_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ContractMilestone $contractMilestone = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'La désignation est obligatoire.')]
    private string $designation = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    private string $quantity = '1.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $unitPriceHt = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4)]
    private string $vatRate = '19.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $amountHt = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $vatAmount = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $amountTtc = '0.000';

    #[ORM\Column(type: Types::INTEGER)]
    private int $position = 0;

    public function __construct()
    {
        $this->ensureId();
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getContractMilestone(): ?ContractMilestone
    {
        return $this->contractMilestone;
    }

    public function setContractMilestone(?ContractMilestone $contractMilestone): static
    {
        $this->contractMilestone = $contractMilestone;

        return $this;
    }

    public function getDesignation(): string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = trim($designation);

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = Money::normalize($quantity);
        $this->recompute();

        return $this;
    }

    public function getUnitPriceHt(): string
    {
        return $this->unitPriceHt;
    }

    public function setUnitPriceHt(string $unitPriceHt): static
    {
        $this->unitPriceHt = Money::normalize($unitPriceHt);
        $this->recompute();

        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = Money::normalize($vatRate, Money::RATE_SCALE);
        $this->recompute();

        return $this;
    }

    public function getAmountHt(): string
    {
        return $this->amountHt;
    }

    public function getVatAmount(): string
    {
        return $this->vatAmount;
    }

    public function getAmountTtc(): string
    {
        return $this->amountTtc;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function recompute(): void
    {
        $this->amountHt = Money::mul($this->quantity, $this->unitPriceHt);
        $this->vatAmount = Money::percentOf($this->amountHt, $this->vatRate);
        $this->amountTtc = Money::add($this->amountHt, $this->vatAmount);
    }
}
