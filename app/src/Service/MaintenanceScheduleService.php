<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContractMilestone;
use App\Entity\MaintenanceContract;
use App\Enum\CompletionStatus;
use App\Enum\ContractBillingStatus;
use App\Enum\MilestoneType;
use App\Repository\ContractMilestoneRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Génération idempotente des échéances d'un contrat de maintenance (§15.3).
 *
 * La clé de génération combine le contrat de maintenance et la date de début de
 * période : une relance ne crée jamais deux échéances pour la même période.
 */
final class MaintenanceScheduleService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContractMilestoneRepository $milestoneRepository,
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly ContractDateCalculator $dateCalculator,
    ) {
    }

    /**
     * @return list<ContractMilestone> échéances nouvellement créées
     */
    public function generate(MaintenanceContract $maintenance, bool $flush = true): array
    {
        $contract = $maintenance->getContract();
        $start = $maintenance->getMaintenanceStartDate();
        if (null === $contract || null === $start) {
            return [];
        }

        $end = $maintenance->getMaintenanceEndDate() ?? $start->modify(sprintf('+%d months', $maintenance->getDurationMonths()));
        $periodMonths = $maintenance->getBillingFrequency()->months();
        $periodAmount = $this->periodicAmount($maintenance);
        $created = [];

        $periodStart = $start;
        $guard = 0;
        while ($periodStart <= $end && $guard < 240) {
            ++$guard;
            $periodEnd = $periodStart->modify(sprintf('+%d months -1 day', $periodMonths));
            if ($periodEnd > $end) {
                $periodEnd = $end;
            }

            $generationKey = sprintf('maintenance:%s:%s', $maintenance->getIdString(), $periodStart->format('Y-m-d'));
            if (null === $this->milestoneRepository->findOneByGenerationKey($generationKey)) {
                $milestone = new ContractMilestone();
                $milestone->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_CONTRACT_MILESTONE))
                    ->setContract($contract)
                    ->setProject($contract->getProject())
                    ->setMaintenanceContract($maintenance)
                    ->setMilestoneType(MilestoneType::MaintenancePeriod)
                    ->setLabel(sprintf(
                        'Maintenance %s — %s au %s',
                        mb_strtolower($maintenance->getBillingFrequency()->label()),
                        $periodStart->format('d/m/Y'),
                        $periodEnd->format('d/m/Y'),
                    ))
                    ->setPlannedDate($periodStart)
                    ->setAmountHt($periodAmount)
                    ->setVatRate($maintenance->getVatRate())
                    ->setCompletionStatus(CompletionStatus::Upcoming)
                    ->setBillingStatus(ContractBillingStatus::NotBillable)
                    ->setResponsible($maintenance->getCommercialResponsible() ?? $contract->getResponsible())
                    ->setGenerationKey($generationKey)
                    ->setConditions('Échéance générée depuis le contrat de maintenance '.$maintenance->getReference().'.');

                $this->entityManager->persist($milestone);
                $created[] = $milestone;
            }

            $periodStart = $periodStart->modify(sprintf('+%d months', $periodMonths));
        }

        if ($flush && [] !== $created) {
            $this->entityManager->flush();
        }

        return $created;
    }

    /**
     * Montant d'une période : le montant périodique s'il est renseigné, sinon
     * le montant annuel réparti selon la périodicité.
     */
    public function periodicAmount(MaintenanceContract $maintenance): string
    {
        if (Money::isPositive($maintenance->getPeriodicAmountHt())) {
            return $maintenance->getPeriodicAmountHt();
        }

        if (Money::isPositive($maintenance->getAnnualAmountHt())) {
            return Money::div($maintenance->getAnnualAmountHt(), (string) $maintenance->getBillingFrequency()->periodsPerYear());
        }

        return Money::zero();
    }

    /** Recalcule le montant annuel lorsqu'il n'est pas saisi. */
    public function refreshAnnualAmount(MaintenanceContract $maintenance): void
    {
        $maintenance->recomputeAnnualAmount();
        if (Money::isZero($maintenance->getAnnualAmountHt()) && Money::isPositive($maintenance->getPeriodicAmountHt())) {
            $maintenance->setAnnualAmountHt(Money::mul(
                $maintenance->getPeriodicAmountHt(),
                (string) $maintenance->getBillingFrequency()->periodsPerYear(),
            ));
        }
    }
}
