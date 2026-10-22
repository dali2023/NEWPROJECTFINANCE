<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Entity\Trait\UuidIdTrait;
use App\Enum\PaymentMethod;
use App\Repository\ExpensePaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Paiement d'une charge (M05). */
#[ORM\Entity(repositoryClass: ExpensePaymentRepository::class)]
#[ORM\Table(name: 'expense_payments')]
#[ORM\UniqueConstraint(name: 'uniq_expense_payments_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_expense_payments_expense', columns: ['expense_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence de paiement existe déjà.')]
class ExpensePayment
{
    use UuidIdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 40)]
    private string $reference = '';

    #[ORM\ManyToOne(targetEntity: Expense::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'expense_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Expense $expense = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $paymentDate;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    #[Assert\Positive(message: 'Le montant doit être positif.')]
    private string $amount = '0.000';

    #[ORM\Column(type: Types::STRING, length: 30, enumType: PaymentMethod::class)]
    private PaymentMethod $method = PaymentMethod::BankTransfer;

    #[ORM\ManyToOne(targetEntity: BankAccount::class)]
    #[ORM\JoinColumn(name: 'bank_account_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?BankAccount $bankAccount = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $bankReference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->ensureId();
        $this->paymentDate = new \DateTimeImmutable('today');
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

    public function getExpense(): ?Expense
    {
        return $this->expense;
    }

    public function setExpense(?Expense $expense): static
    {
        $this->expense = $expense;

        return $this;
    }

    public function getPaymentDate(): \DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeImmutable $paymentDate): static
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = \App\Service\Money::normalize($amount);

        return $this;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getBankAccount(): ?BankAccount
    {
        return $this->bankAccount;
    }

    public function setBankAccount(?BankAccount $bankAccount): static
    {
        $this->bankAccount = $bankAccount;

        return $this;
    }

    public function getBankReference(): ?string
    {
        return $this->bankReference;
    }

    public function setBankReference(?string $bankReference): static
    {
        $this->bankReference = $bankReference;

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
}
