<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\ExpenseRepository;
use App\Repository\FiscalYearRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectMilestoneRepository;
use App\Repository\ProjectRepository;
use App\Repository\SupplierRepository;
use App\Enum\ProjectStatus;
use App\Security\PermissionChecker;
use App\Service\TreasuryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran 02 — Tableau de bord général (§7.2).
 *
 * Les indicateurs financiers sont affichés lorsqu'ils sont effectivement
 * calculables et que l'utilisateur possède la permission correspondante. Un
 * tableau de bord financier ne doit jamais afficher un montant faux.
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
        InvoiceRepository $invoiceRepository,
        ExpenseRepository $expenseRepository,
        TreasuryService $treasuryService,
        PermissionChecker $permissionChecker,
    ): Response {
        $user = $this->getUser();
        $scope = $permissionChecker->projectScope($user);
        $canViewInvoices = $permissionChecker->has($user, 'invoice.view');
        $canViewTreasury = $permissionChecker->has($user, 'treasury.view');
        $canViewExpenses = $permissionChecker->has($user, 'expense.view');

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
            'invoice_open_balance' => $canViewInvoices ? $invoiceRepository->sumOpenBalance() : null,
            'invoice_overdue_balance' => $canViewInvoices ? $invoiceRepository->sumOverdueBalance() : null,
            'expense_pending_total' => $canViewExpenses ? $expenseRepository->sumPending() : null,
            'treasury_balance' => $canViewTreasury ? $treasuryService->totalBalance() : null,
        ]);
    }
}
