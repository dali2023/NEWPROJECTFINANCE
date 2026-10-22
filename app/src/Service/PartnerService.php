<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\PartnerTransaction;
use App\Entity\User;
use App\Enum\PartnerTransactionStatus;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Opérations avec les associés (M10).
 *
 * Les apports, remboursements et dividendes sont validés puis réglés. Le
 * règlement peut générer un mouvement de trésorerie de même date et de même
 * montant, sans double saisie.
 */
final class PartnerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TreasuryService $treasuryService,
    ) {
    }

    public function validate(PartnerTransaction $transaction, User $user): void
    {
        $transaction->validate($user);
    }

    public function pay(PartnerTransaction $transaction, ?BankAccount $account, User $user): void
    {
        if (null === $account) {
            throw new \LogicException('Un compte de trésorerie est nécessaire pour régler l’opération.');
        }

        if (PartnerTransactionStatus::Validated !== $transaction->getStatus()) {
            throw new \LogicException('Une opération doit être validée avant d’être réglée.');
        }

        $transaction->markPaid();

        $direction = $transaction->getTransactionType()->isInflow()
            ? TreasuryDirection::Inflow
            : TreasuryDirection::Outflow;
        $type = match ($transaction->getTransactionType()) {
            \App\Enum\PartnerTransactionType::CapitalContribution,
            \App\Enum\PartnerTransactionType::CurrentAccountContribution => TreasuryMovementType::PartnerContribution,
            \App\Enum\PartnerTransactionType::CurrentAccountRepayment => TreasuryMovementType::PartnerRepayment,
            \App\Enum\PartnerTransactionType::Dividend => TreasuryMovementType::Dividend,
            default => TreasuryMovementType::Other,
        };

        $movement = $this->treasuryService->record(
            $account,
            $direction,
            $type,
            $transaction->getAmount(),
            sprintf('%s — %s', $transaction->getTransactionType()->label(), $transaction->getPartner()?->getName() ?? ''),
            $user,
            'PartnerTransaction',
            $transaction->getIdString(),
            $transaction->getTransactionDate(),
            $transaction->getNotes(),
        );
        $this->entityManager->persist($movement);
    }

    public function cancel(PartnerTransaction $transaction): void
    {
        if (PartnerTransactionStatus::Paid === $transaction->getStatus()) {
            throw new \LogicException('Une opération réglée ne peut pas être annulée.');
        }

        $transaction->setStatus(PartnerTransactionStatus::Cancelled);
    }
}
