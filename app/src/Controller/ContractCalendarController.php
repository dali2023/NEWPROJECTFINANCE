<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contract;
use App\Enum\CompletionStatus;
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
        $view = $request->query->getString('vue', 'agenda');
        if (!\in_array($view, ['agenda', 'mois'], true)) {
            $view = 'agenda';
        }

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

            foreach ($contract->getMilestones() as $milestone) {
                if (CompletionStatus::Cancelled === $milestone->getCompletionStatus()) {
                    continue;
                }
                $this->addEvent($events, $contract, $milestone->getPlannedDate(), 'milestone', 'Échéance : '.$milestone->getLabel(), $today, $limit, $type);
            }

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

        $eventsByDay = [];
        foreach ($events as $event) {
            $eventsByDay[$event['date']->format('Y-m-d')][] = $event;
        }

        $agendaDays = $this->groupByDay($events);

        $firstMonth = $today->modify('first day of this month')->setTime(0, 0);
        $lastMonth = $limit->modify('first day of this month')->setTime(0, 0);
        $monthStart = $this->resolveMonth($request->query->getString('mois', ''), $firstMonth, $lastMonth);
        $monthEvents = array_values(array_filter(
            $events,
            static fn (array $event): bool => $event['date']->format('Y-m') === $monthStart->format('Y-m'),
        ));

        return $this->render('contract/calendar.html.twig', [
            'events' => $events,
            'agenda_days' => $agendaDays,
            'month_agenda_days' => $this->groupByDay($monthEvents),
            'month_events_count' => \count($monthEvents),
            'month_start' => $monthStart,
            'month_end' => $monthStart->modify('last day of this month'),
            'weeks' => $this->buildMonthGrid($monthStart, $eventsByDay, $today),
            'prev_month' => $monthStart > $firstMonth ? $monthStart->modify('-1 month') : null,
            'next_month' => $monthStart < $lastMonth ? $monthStart->modify('+1 month') : null,
            'horizon' => $horizon,
            'type' => $type,
            'view' => $view,
            'today' => $today,
            'tomorrow' => $today->modify('+1 day'),
            'event_types' => [
                'all' => 'Toutes les échéances',
                'milestone' => 'Échéances de jalons',
                'renewal' => 'Renouvellements à préparer',
                'end' => 'Fins de contrat',
                'maintenance' => 'Fins de maintenance',
                'receipt' => 'Réceptions prévues',
                'warranty' => 'Fins de garantie',
                'retention' => 'Retenues de garantie',
            ],
            'event_type_labels' => [
                'milestone' => 'Jalon',
                'renewal' => 'Renouvellement',
                'end' => 'Fin de contrat',
                'maintenance' => 'Maintenance',
                'receipt' => 'Réception',
                'warranty' => 'Garantie',
                'retention' => 'Retenue',
            ],
        ]);
    }

    /**
     * Regroupe les échéances par journée pour l'affichage agenda.
     *
     * @param list<array{date: \DateTimeImmutable, type: string, label: string, contract: Contract}> $events
     *
     * @return list<array{date: \DateTimeImmutable, events: list<array{date: \DateTimeImmutable, type: string, label: string, contract: Contract}>}>
     */
    private function groupByDay(array $events): array
    {
        $days = [];
        foreach ($events as $event) {
            $key = $event['date']->format('Y-m-d');
            $days[$key] ??= ['date' => $event['date'], 'events' => []];
            $days[$key]['events'][] = $event;
        }

        return array_values($days);
    }

    private function resolveMonth(string $requested, \DateTimeImmutable $firstMonth, \DateTimeImmutable $lastMonth): \DateTimeImmutable
    {
        $month = $firstMonth;
        if (1 === preg_match('/^\d{4}-\d{2}$/', $requested)) {
            $candidate = \DateTimeImmutable::createFromFormat('!Y-m-d', $requested.'-01');
            if (false !== $candidate) {
                $month = $candidate->setTime(0, 0);
            }
        }

        if ($month < $firstMonth) {
            return $firstMonth;
        }

        if ($month > $lastMonth) {
            return $lastMonth;
        }

        return $month;
    }

    /**
     * Construit la grille mensuelle (semaines complètes du lundi au dimanche).
     *
     * @param array<string, list<array{date: \DateTimeImmutable, type: string, label: string, contract: Contract}>> $eventsByDay
     *
     * @return list<list<array{date: \DateTimeImmutable, in_month: bool, is_today: bool, events: list<array{date: \DateTimeImmutable, type: string, label: string, contract: Contract}>}>>
     */
    private function buildMonthGrid(\DateTimeImmutable $monthStart, array $eventsByDay, \DateTimeImmutable $today): array
    {
        $cursor = $monthStart->modify('monday this week');
        $gridEnd = $monthStart->modify('last day of this month')->modify('sunday this week');
        $weeks = [];

        while ($cursor <= $gridEnd) {
            $week = [];
            for ($day = 0; $day < 7; ++$day) {
                $date = $cursor->modify(sprintf('+%d days', $day));
                $week[] = [
                    'date' => $date,
                    'in_month' => $date->format('Y-m') === $monthStart->format('Y-m'),
                    'is_today' => $date->format('Y-m-d') === $today->format('Y-m-d'),
                    'events' => $eventsByDay[$date->format('Y-m-d')] ?? [],
                ];
            }
            $weeks[] = $week;
            $cursor = $cursor->modify('+7 days');
        }

        return $weeks;
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
