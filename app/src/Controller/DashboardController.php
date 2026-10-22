<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\FiscalYearRepository;
use App\Repository\ProjectMilestoneRepository;
use App\Repository\ProjectRepository;
use App\Repository\SupplierRepository;
use App\Enum\ProjectStatus;
use App\Security\PermissionChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran 02 — Tableau de bord général (§7.2).
 *
 * Les indicateurs dépendant des modules de facturation, charges et trésorerie
 * (phases 3 et 4) sont signalés comme non disponibles plutôt que valorisés à
 * zéro : un tableau de bord financier ne doit jamais afficher un montant faux.
 */
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(
        ProjectRepository $projectRepository,
        ProjectMilestoneRepository $milestoneRepository,
        ClientRepository $clientRepository,
        SupplierRepository $supplierRepository,
        FiscalYearRepository $fiscalYearRepository,
        PermissionChecker $permissionChecker,
    ): Response {
        $user = $this->getUser();
        $scope = $permissionChecker->projectScope($user);

        $projects = $projectRepository->search(['onlyFor' => $scope], 1, 5);
        $fiscalYear = $fiscalYearRepository->findCurrent();

        $statusCounts = $projectRepository->countByStatus();
        $statusBreakdown = array_map(
            static fn (ProjectStatus $status): array => [
                'status' => $status,
                'count' => $statusCounts[$status->value] ?? 0,
            ],
            ProjectStatus::cases(),
        );

        return $this->render('dashboard/index.html.twig', [
            'contract_total' => $projectRepository->sumContractualAmountHt(),
            'project_count' => $projectRepository->countAll(),
            'status_breakdown' => $statusBreakdown,
            'recent_projects' => $projects['items'],
            'project_total' => $projects['total'],
            'upcoming_milestones' => $milestoneRepository->findUpcoming(new \DateTimeImmutable('today'), null, 6),
            'active_clients' => $clientRepository->countActive(),
            'active_suppliers' => \count($supplierRepository->findActiveOrdered()),
            'fiscal_year' => $fiscalYear,
        ]);
    }
}
