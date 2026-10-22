<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use App\Entity\ContractReceipt;
use App\Entity\MaintenanceContract;

/**
 * Calculs de dates contractuelles (§15.4, §15.5, §15.10 A et B).
 *
 * Tous les calculs sont effectués sur des dates civiles (sans heure) afin
 * d'éviter les décalages liés aux fuseaux horaires.
 */
final class ContractDateCalculator
{
    public function __construct(private readonly string $timezone = 'Africa/Tunis')
    {
    }

    public function today(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today', new \DateTimeZone($this->timezone)))->setTime(0, 0);
    }

    /** Date limite de préparation = date d'expiration − préavis. */
    public function renewalPreparationDate(?Contract $contract): ?\DateTimeImmutable
    {
        if (null === $contract || null === $contract->getEffectiveEndDate()) {
            return null;
        }

        return $contract->getEffectiveEndDate()->modify(sprintf('-%d days', $contract->getNoticePeriodDays()));
    }

    public function maintenanceRenewalPreparationDate(?MaintenanceContract $maintenance): ?\DateTimeImmutable
    {
        return $maintenance?->getRenewalPreparationDate();
    }

    /**
     * Date indicative de réception définitive = réception provisoire effective
     * + durée de garantie contractuelle, ajustable par avenant (§15.10 B).
     */
    public function indicativeFinalReceiptDate(
        ?\DateTimeImmutable $provisionalActualDate,
        int $warrantyDurationDays,
        int $adjustmentDays = 0,
    ): ?\DateTimeImmutable {
        if (null === $provisionalActualDate) {
            return null;
        }

        return $provisionalActualDate->modify(sprintf('+%d days', max(0, $warrantyDurationDays + $adjustmentDays)));
    }

    public function indicativeFinalReceiptDateForContract(Contract $contract, int $adjustmentDays = 0): ?\DateTimeImmutable
    {
        $provisional = null;
        foreach ($contract->getReceipts() as $receipt) {
            if ($receipt->isProvisional() && null !== $receipt->getActualDate()) {
                if (null === $provisional || $receipt->getActualDate() > $provisional) {
                    $provisional = $receipt->getActualDate();
                }
            }
        }

        return $this->indicativeFinalReceiptDate($provisional, $contract->getWarrantyDurationDays(), $adjustmentDays);
    }

    public function daysUntil(\DateTimeImmutable $date, ?\DateTimeImmutable $from = null): int
    {
        $from ??= $this->today();

        return (int) $from->diff($date)->format('%r%a');
    }

    public function isWithin(\DateTimeImmutable $date, int $days, ?\DateTimeImmutable $from = null): bool
    {
        $from ??= $this->today();
        $difference = $this->daysUntil($date, $from);

        return $difference >= 0 && $difference <= $days;
    }

    /** Prochaine date d'action : préparation au renouvellement ou expiration. */
    public function nextActionDate(Contract $contract): ?\DateTimeImmutable
    {
        $dates = array_filter([
            $contract->getRenewalPreparationDate(),
            $contract->getEffectiveEndDate(),
        ]);

        if ([] === $dates) {
            return null;
        }

        usort($dates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);

        return $dates[0];
    }

    /** Date de fin de garantie la plus tardive enregistrée sur les réceptions. */
    public function latestWarrantyEndDate(Contract $contract): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($contract->getReceipts() as $receipt) {
            if (null !== $receipt->getWarrantyEndDate()
                && (null === $latest || $receipt->getWarrantyEndDate() > $latest)) {
                $latest = $receipt->getWarrantyEndDate();
            }
        }

        return $latest;
    }

    /** Date de fin de garantie d'une réception, calculée si absente. */
    public function warrantyEndDateForReceipt(ContractReceipt $receipt, Contract $contract): ?\DateTimeImmutable
    {
        if (null !== $receipt->getWarrantyEndDate()) {
            return $receipt->getWarrantyEndDate();
        }

        $start = $receipt->getWarrantyStartDate() ?? $receipt->getActualDate();

        return $start?->modify(sprintf('+%d days', $contract->getWarrantyDurationDays()));
    }
}
