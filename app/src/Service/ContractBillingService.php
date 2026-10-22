<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use App\Entity\ContractMilestone;
use App\Entity\RetentionGuarantee;
use App\Enum\ContractBillingStatus;
use App\Enum\MilestoneType;
use App\Repository\ContractMilestoneRepository;
use App\Repository\RetentionGuaranteeRepository;

/**
 * Calculs financiers contractuels (§15.10 C à F).
 */
final class ContractBillingService
{
    public function __construct(
        private readonly ContractMilestoneRepository $milestoneRepository,
        private readonly RetentionGuaranteeRepository $retentionRepository,
    ) {
    }

    /**
     * Reste à facturer du contrat = montant contractuel facturable − déjà
     * facturé. Le montant facturable retient le plus élevé du montant du
     * contrat et de la somme des échéances, afin de tenir compte des avenants
     * sans produire de reste négatif.
     */
    public function remainingToInvoice(Contract $contract): string
    {
        $billable = Money::max($contract->getAmountHt(), $contract->getMilestonesTotalHt());
        $invoiced = $contract->getInvoicedTotalHt();

        if (Money::compare($invoiced, $billable) >= 0) {
            return Money::zero();
        }

        return Money::sub($billable, $invoiced);
    }

    /** Somme restant à facturer sur les échéances non annulées. */
    public function remainingFromMilestones(Contract $contract): string
    {
        return Money::sum(array_map(
            static fn (ContractMilestone $milestone): string => $milestone->isCancelled() ? Money::zero() : $milestone->getRemainingToInvoiceHt(),
            $contract->getMilestones()->toArray(),
        ));
    }

    /** @return list<ContractMilestone> */
    public function overdueMilestones(?\DateTimeImmutable $today = null, ?Contract $contract = null): array
    {
        return $this->milestoneRepository->findOverdue($today, null, $contract);
    }

    public function overdueTotalHt(?\DateTimeImmutable $today = null, ?Contract $contract = null): string
    {
        return Money::sum(array_map(
            static fn (ContractMilestone $milestone): string => $milestone->getRemainingToInvoiceHt(),
            $this->overdueMilestones($today, $contract),
        ));
    }

    public function readyToInvoiceTotalHt(): string
    {
        return Money::sum(array_map(
            static fn (ContractMilestone $milestone): string => $milestone->getRemainingToInvoiceHt(),
            $this->milestoneRepository->findReadyToInvoice(),
        ));
    }

    /**
     * Revenus prévisionnels de maintenance : échéances futures actives et non
     * annulées. Les renouvellements non confirmés sont présentés séparément
     * dans l'interface.
     */
    public function forecastMaintenanceRevenue(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): string
    {
        return Money::sum(array_map(
            static fn (ContractMilestone $milestone): string => $milestone->getAmountHt(),
            $this->milestoneRepository->findForecastMaintenance($from, $to),
        ));
    }

    /** Montant restant à libérer au titre des retenues de garantie (§15.10 F). */
    public function retentionRemaining(?Contract $contract = null): string
    {
        $guarantees = null !== $contract
            ? $this->retentionRepository->findForContract($contract)
            : $this->retentionRepository->findPending();

        return Money::sum(array_map(
            static fn (RetentionGuarantee $guarantee): string => $guarantee->getRemainingToRelease(),
            $guarantees,
        ));
    }

    public function markReady(ContractMilestone $milestone): void
    {
        $milestone->setBillingStatus(ContractBillingStatus::ReadyToInvoice);
    }

    public function markToReview(ContractMilestone $milestone): void
    {
        $milestone->setBillingStatus(ContractBillingStatus::ToReview);
    }

    public function block(ContractMilestone $milestone, string $reason): void
    {
        $milestone->setBillingStatus(ContractBillingStatus::Blocked);
        $milestone->setBlockedReason($reason);
    }

    public function unblock(ContractMilestone $milestone): void
    {
        $milestone->setBlockedReason(null);
        $milestone->setBillingStatus(
            Money::isPositive($milestone->getBilledAmountHt())
                ? ContractBillingStatus::PartiallyInvoiced
                : ContractBillingStatus::ToReview,
        );
    }

    public function complete(ContractMilestone $milestone, ?\DateTimeImmutable $actualDate = null): void
    {
        $milestone->setCompletionStatus(\App\Enum\CompletionStatus::Completed);
        $milestone->setActualDate($actualDate ?? new \DateTimeImmutable('today'));
        if (ContractBillingStatus::NotBillable === $milestone->getBillingStatus()) {
            $milestone->setBillingStatus(ContractBillingStatus::ToReview);
        }
    }

    public function postpone(ContractMilestone $milestone, \DateTimeImmutable $newDate, string $reason, ?\App\Entity\User $user = null): void
    {
        $history = new \App\Entity\ContractMilestoneHistory();
        $history->setOldPlannedDate($milestone->getPlannedDate())
            ->setNewPlannedDate($newDate)
            ->setReason($reason)
            ->setChangedBy($user);
        $milestone->addHistory($history);
        $milestone->setPlannedDate($newDate);
    }

    public function isMaintenanceMilestone(ContractMilestone $milestone): bool
    {
        return $milestone->getMilestoneType()->isMaintenance()
            || MilestoneType::AnnualRenewal === $milestone->getMilestoneType();
    }
}
