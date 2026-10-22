<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\CashMovement;
use App\Entity\User;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use App\Repository\BankAccountRepository;
use App\Repository\CashMovementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Mouvements de trésorerie (M09).
 *
 * Chaque encaissement ou décaissement produit un mouvement rattaché à un
 * compte. Le solde est recalculé à partir du solde d'ouverture et des
 * mouvements, jamais saisi manuellement.
 */
final class TreasuryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CashMovementRepository $movementRepository,
        private readonly BankAccountRepository $bankAccountRepository,
        private readonly ReferenceGenerator $referenceGenerator,
    ) {
    }

    public function record(
        BankAccount $account,
        TreasuryDirection $direction,
        TreasuryMovementType $type,
        string $amount,
        string $label,
        ?User $user = null,
        ?string $sourceType = null,
        ?string $sourceId = null,
        ?\DateTimeImmutable $date = null,
        ?string $notes = null,
    ): CashMovement {
        $movement = new CashMovement();
        $movement->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_TRANSFER))
            ->setBankAccount($account)
            ->setMovementDate($date ?? new \DateTimeImmutable('today'))
            ->setDirection($direction)
            ->setMovementType($type)
            ->setAmount($amount)
            ->setLabel($label)
            ->setSourceType($sourceType)
            ->setSourceId($sourceId)
            ->setNotes($notes)
            ->setCreatedBy($user);

        $account->addMovement($movement);

        return $movement;
    }

    public function totalBalance(): string
    {
        return Money::sum(array_map(
            static fn (BankAccount $account): string => $account->getBalance(),
            $this->bankAccountRepository->findAll(),
        ));
    }

    public function inflows(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): string
    {
        return $this->movementRepository->sum(TreasuryDirection::Inflow, $from, $to);
    }

    public function outflows(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): string
    {
        return $this->movementRepository->sum(TreasuryDirection::Outflow, $from, $to);
    }

    public function reconcile(CashMovement $movement, User $user): void
    {
        $movement->reconcile($user);
    }
}
