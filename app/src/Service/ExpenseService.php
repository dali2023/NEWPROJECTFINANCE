<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\Expense;
use App\Entity\ExpensePayment;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use App\Enum\PaymentMethod;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cycle de vie des charges (M05).
 *
 * L'auteur d'une dépense ne peut pas l'approuver. Un paiement validé crée un
 * décaissement de trésorerie et réduit le solde de la charge dans la même
 * transaction.
 */
final class ExpenseService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly TreasuryService $treasuryService,
    ) {
    }

    public function submit(Expense $expense): void
    {
        $expense->submit();
    }

    public function approve(Expense $expense, User $approver): void
    {
        $expense->approve($approver);
    }

    public function reject(Expense $expense, User $approver, string $reason): void
    {
        if ('' === trim($reason)) {
            throw new \LogicException('Le motif de rejet est obligatoire.');
        }

        $expense->reject($approver, $reason);
    }

    public function registerPayment(
        Expense $expense,
        string $amount,
        \DateTimeImmutable $paymentDate,
        PaymentMethod $method,
        ?BankAccount $account,
        ?string $bankReference,
        ?string $notes,
        User $user,
    ): ExpensePayment {
        if (!\in_array($expense->getStatus(), [ExpenseStatus::Approved, ExpenseStatus::PartiallyPaid], true)) {
            throw new \LogicException('Une dépense doit être approuvée avant d’être payée.');
        }

        $amount = Money::normalize($amount);
        if (!Money::isPositive($amount)) {
            throw new \LogicException('Le montant du paiement doit être positif.');
        }

        if (Money::compare($amount, $expense->getBalance()) > 0) {
            throw new \LogicException('Le paiement dépasse le solde restant de la dépense.');
        }

        $payment = new ExpensePayment();
        $payment->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_PAYMENT))
            ->setExpense($expense)
            ->setPaymentDate($paymentDate)
            ->setAmount($amount)
            ->setMethod($method)
            ->setBankAccount($account)
            ->setBankReference($bankReference)
            ->setNotes($notes)
            ->setCreatedBy($user);

        $expense->addPayment($payment);
        $expense->recomputePaidAmount();

        if (null !== $account) {
            $movement = $this->treasuryService->record(
                $account,
                TreasuryDirection::Outflow,
                TreasuryMovementType::ExpensePayment,
                $amount,
                sprintf('Paiement dépense %s', $expense->getReference()),
                $user,
                'ExpensePayment',
                $payment->getIdString(),
                $paymentDate,
                $bankReference,
            );
            $this->entityManager->persist($movement);
        }

        $this->entityManager->persist($payment);

        return $payment;
    }
}
