<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use App\Entity\ContractReceipt;
use App\Enum\ContractStatus;
use App\Enum\MaintenanceRenewalStatus;
use App\Enum\ReceiptType;

/**
 * Détermination du statut contractuel à partir des dates et des données
 * validées (§15.2).
 *
 * Le statut calculé ne déclenche aucun renouvellement : il rend seulement
 * visible une échéance. L'action de renouvellement reste explicite et soumise à
 * la permission contract.renew.
 */
final class ContractStatusResolver
{
    public function __construct(private readonly ContractDateCalculator $dateCalculator)
    {
    }

    public function resolve(Contract $contract, ?\DateTimeImmutable $today = null): ContractStatus
    {
        $today ??= $this->dateCalculator->today();
        $current = $contract->getStatus();

        // Les statuts pilotés par une action explicite restent inchangés.
        if (\in_array($current, [ContractStatus::Draft, ContractStatus::PendingSignature, ContractStatus::Terminated, ContractStatus::Closed], true)) {
            return $current;
        }

        $endDate = $contract->getEffectiveEndDate();
        if (null !== $endDate && $endDate < $today) {
            return ContractStatus::Expired;
        }

        $maintenance = $contract->getActiveMaintenanceContract($today);
        if (null !== $maintenance) {
            $preparation = $maintenance->getRenewalPreparationDate();
            if (null !== $preparation
                && $preparation <= $today
                && !\in_array($maintenance->getRenewalStatus(), [MaintenanceRenewalStatus::Renewed, MaintenanceRenewalStatus::NotRenewed, MaintenanceRenewalStatus::Terminated], true)) {
                return ContractStatus::ToRenew;
            }

            return ContractStatus::ActiveMaintenance;
        }

        $warrantyEnd = $this->latestWarrantyEnd($contract);
        if (null !== $warrantyEnd && $warrantyEnd >= $today) {
            $preparation = $contract->getRenewalPreparationDate();
            if (null !== $preparation && $preparation <= $today) {
                return ContractStatus::ToRenew;
            }

            return ContractStatus::Warranty;
        }

        $preparation = $contract->getRenewalPreparationDate();
        if (null !== $preparation && $preparation <= $today && (null === $endDate || $endDate >= $today)) {
            return ContractStatus::ToRenew;
        }

        if (null !== $endDate && $this->dateCalculator->isWithin($endDate, 90, $today)) {
            return ContractStatus::Expiring;
        }

        $start = $contract->getEffectiveStartDate() ?? $contract->getExecutionStartDate();
        if (null !== $start && $start <= $today) {
            return ContractStatus::InProgress;
        }

        return ContractStatus::Signed;
    }

    /** Applique le statut calculé au contrat et indique s'il a changé. */
    public function refresh(Contract $contract, ?\DateTimeImmutable $today = null): bool
    {
        $resolved = $this->resolve($contract, $today);
        if ($resolved === $contract->getStatus()) {
            return false;
        }

        $contract->setStatus($resolved);

        return true;
    }

    private function latestWarrantyEnd(Contract $contract): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($contract->getReceipts() as $receipt) {
            if (ReceiptType::Final === $receipt->getReceiptType() && null !== $receipt->getWarrantyEndDate()) {
                if (null === $latest || $receipt->getWarrantyEndDate() > $latest) {
                    $latest = $receipt->getWarrantyEndDate();
                }
            }
        }

        return $latest;
    }
}
