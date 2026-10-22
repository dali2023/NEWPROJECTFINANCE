<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\CashMovement;
use App\Entity\Expense;
use App\Entity\ExpensePayment;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Payment;
use App\Entity\PartnerTransaction;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\PartnerTransactionType;
use App\Enum\PaymentMethod;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Services transactionnels de facturation (M04).
 *
 * La validation d'une facture issue d'une échéance contractuelle reporte la
 * référence et le montant sur l'échéance. Le statut de l'échéance n'est jamais
 * mis à « facturée » simplement parce qu'elle est prête.
 */
final class InvoiceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly TreasuryService $treasuryService,
        private readonly string $currency = 'TND',
    ) {
    }

    public function createDraft(
        \App\Entity\Client $client,
        ?\App\Entity\Project $project = null,
        ?\App\Entity\Contract $contract = null,
        ?\App\Entity\ContractMilestone $milestone = null,
        ?User $user = null,
        ?\DateTimeImmutable $issueDate = null,
    ): Invoice {
        $issueDate ??= new \DateTimeImmutable('today');
        $invoice = new Invoice();
        $invoice->setNumber($this->referenceGenerator->next(ReferenceGenerator::MODULE_INVOICE))
            ->setInvoiceType(InvoiceType::Invoice)
            ->setStatus(InvoiceStatus::Draft)
            ->setClient($client)
            ->setProject($project ?? $milestone?->getProject())
            ->setContract($contract ?? $milestone?->getContract())
            ->setContractMilestone($milestone)
            ->setIssueDate($issueDate)
            ->setDueDate($issueDate->modify(sprintf('+%d days', $client->getPaymentTermsDays())))
            ->setCurrencyCode($contract?->getCurrencyCode() ?? $this->currency)
            ->setCreatedBy($user);

        $line = new InvoiceLine();
        $line->setDesignation(null !== $milestone
            ? sprintf('Échéance %s — %s', $milestone->getReference(), $milestone->getLabel())
            : 'Prestation')
            ->setQuantity('1')
            ->setUnitPriceHt(null !== $milestone ? $milestone->getRemainingToInvoiceHt() : '0')
            ->setVatRate(null !== $milestone ? $milestone->getVatRate() : '19')
            ->setContractMilestone($milestone);
        $line->recompute();
        $invoice->addLine($line);
        $invoice->recomputeTotals();

        $this->entityManager->persist($invoice);

        return $invoice;
    }

    public function validate(Invoice $invoice, User $user): void
    {
        $invoice->validate($user);

        $milestone = $invoice->getContractMilestone();
        if (null !== $milestone) {
            $milestone->invoice($invoice->getAmountHt(), $invoice->getNumber(), $invoice->getIssueDate());
        }
    }

    public function markSent(Invoice $invoice): void
    {
        $invoice->markSent();
    }

    /**
     * Enregistre un règlement et, si un compte est fourni, le mouvement de
     * trésorerie correspondant dans la même unité de travail.
     */
    public function registerPayment(
        Invoice $invoice,
        string $amount,
        \DateTimeImmutable $paymentDate,
        PaymentMethod $method,
        ?BankAccount $account,
        ?string $bankReference,
        ?string $notes,
        User $user,
    ): Payment {
        if (\in_array($invoice->getStatus(), [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
            throw new \LogicException('Une facture doit être validée avant de pouvoir être encaissée.');
        }

        $amount = Money::normalize($amount);
        if (!Money::isPositive($amount)) {
            throw new \LogicException('Le montant du règlement doit être positif.');
        }

        if (Money::compare($amount, $invoice->getBalance()) > 0) {
            throw new \LogicException('Le règlement dépasse le solde restant de la facture.');
        }

        $payment = new Payment();
        $payment->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_PAYMENT))
            ->setInvoice($invoice)
            ->setPaymentDate($paymentDate)
            ->setAmount($amount)
            ->setMethod($method)
            ->setBankAccount($account)
            ->setBankReference($bankReference)
            ->setNotes($notes)
            ->setCreatedBy($user);

        $invoice->addPayment($payment);
        $invoice->recomputePaidAmount();

        if (null !== $account) {
            $movement = $this->treasuryService->record(
                $account,
                TreasuryDirection::Inflow,
                TreasuryMovementType::ClientReceipt,
                $amount,
                sprintf('Règlement facture %s', $invoice->getNumber()),
                $user,
                'Payment',
                $payment->getIdString(),
                $paymentDate,
                $bankReference,
            );
            $this->entityManager->persist($movement);
        }

        $this->entityManager->persist($payment);

        return $payment;
    }

    public function cancel(Invoice $invoice, User $user, string $reason): void
    {
        $invoice->cancel($user, $reason);
    }
}
