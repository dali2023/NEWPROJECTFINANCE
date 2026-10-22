<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contract;
use App\Repository\ContractRepository;
use App\Security\PermissionChecker;
use App\Service\ContractDateCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran 15 — calendrier et liste des échéances contractuelles (§15.8).
 */
#[Route('/contrats/calendrier', name: 'app_contract_calendar_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ContractCalendarController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ContractRepository $contractRepository,
        ContractDateCalculator $dateCalculator,
        PermissionChecker $permissionChecker,
    ): Response {
        if (!$this->isGranted('contract.view_all') && !$this->isGranted('contract.view_own')) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à consulter le calendrier des contrats.');
        }

        $horizon = max(1, min(24, $request->query->getInt('horizon', 12)));
        $type = $request->query->getString('type', 'all');
        $scope = $permissionChecker->contractScope($this->getUser());
        $today = $dateCalculator->today();
        $limit = $today->modify(sprintf('+%d months', $horizon));

        $events = [];
        foreach ($contractRepository->findActiveWithDates() as $contract) {
            if (null !== $scope && !$contract->isResponsibleFor($scope)) {
                continue;
            }

            $this->addEvent($events, $contract, $contract->getRenewalPreparationDate(), 'renewal', 'Date limite de préparation au renouvellement', $today, $limit, $type);
            $this->addEvent($events, $contract, $contract->getEffectiveEndDate(), 'end', 'Fin contractuelle', $today, $limit, $type);

            foreach ($contract->getMaintenanceContracts() as $maintenance) {
                $this->addEvent($events, $contract, $maintenance->getMaintenanceEndDate(), 'maintenance', 'Fin de maintenance '.$maintenance->getReference(), $today, $limit, $type);
            }

            foreach ($contract->getReceipts() as $receipt) {
                if (null !== $receipt->getPlannedDate() && null === $receipt->getActualDate()) {
                    $this->addEvent(
                        $events,
                        $contract,
                        $receipt->getPlannedDate(),
                        'receipt',
                        $receipt->getReceiptType()->label().' prévue',
                        $today,
                        $limit,
                        $type,
                    );
                }
                $warrantyEnd = $dateCalculator->warrantyEndDateForReceipt($receipt, $contract);
                $this->addEvent($events, $contract, $warrantyEnd, 'warranty', 'Fin de garantie', $today, $limit, $type);
            }

            foreach ($contract->getRetentionGuarantees() as $retention) {
                if (\App\Enum\RetentionStatus::Released !== $retention->getStatus()) {
                    $this->addEvent($events, $contract, $retention->getEligibilityDate(), 'retention', 'Libération de retenue de garantie', $today, $limit, $type);
                }
            }
        }

        usort($events, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $this->render('contract/calendar.html.twig', [
            'events' => $events,
            'horizon' => $horizon,
            'type' => $type,
            'today' => $today,
            'event_types' => [
                'all' => 'Toutes les échéances',
                'renewal' => 'Renouvellements à préparer',
                'end' => 'Fins de contrat',
                'maintenance' => 'Fins de maintenance',
                'receipt' => 'Réceptions prévues',
                'warranty' => 'Fins de garantie',
                'retention' => 'Retenues de garantie',
            ],
        ]);
    }

    /**
     * @param list<array{date: \DateTimeImmutable, type: string, label: string, contract: Contract}> $events
     */
    private function addEvent(
        array &$events,
        Contract $contract,
        ?\DateTimeImmutable $date,
        string $eventType,
        string $label,
        \DateTimeImmutable $today,
        \DateTimeImmutable $limit,
        string $filter,
    ): void {
        if (null === $date || $date < $today || $date > $limit) {
            return;
        }

        if ('all' !== $filter && $filter !== $eventType) {
            return;
        }

        $events[] = [
            'date' => $date,
            'type' => $eventType,
            'label' => $label,
            'contract' => $contract,
        ];
    }
}
