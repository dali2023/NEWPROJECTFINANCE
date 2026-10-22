<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContractMilestone;
use App\Enum\ContractBillingStatus;
use App\Repository\ContractMilestoneRepository;
use App\Repository\ContractNotificationRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Security\Voter\ContractVoter;
use App\Service\AuditLogger;
use App\Service\ContractBillingService;
use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran 16 — facturation à réaliser (§15.8).
 *
 * « Prête à facturer » est un état de préparation : il ne vaut jamais
 * « facturée ». L'enregistrement d'une facture exige la permission
 * contract.bill et reste soumis à l'audit.
 */
#[Route('/contrats/facturation', name: 'app_contract_billing_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ContractBillingController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ContractMilestoneRepository $milestoneRepository,
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
        ContractBillingService $billingService,
        PermissionChecker $permissionChecker,
    ): Response {
        if (!$this->isGranted('contract.view_all') && !$this->isGranted('contract.view_own')) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à consulter la facturation contractuelle.');
        }

        $scope = $permissionChecker->contractScope($this->getUser());
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'project' => $this->findByUuid($projectRepository, $request->query->getString('projet')),
            'billingStatus' => ContractBillingStatus::tryFrom($request->query->getString('statut')),
            'readyOnly' => $request->query->getBoolean('pretes') ?: null,
            'overdueOnly' => $request->query->getBoolean('retard') ?: null,
            'onlyFor' => $scope,
        ];

        return $this->render('contract/billing/index.html.twig', [
            'result' => $milestoneRepository->searchBilling($filters, $request->query->getInt('page', 1)),
            'filters' => $filters,
            'statuses' => ContractBillingStatus::cases(),
            'projects' => $projectRepository->search([], 1, 200)['items'],
            'responsibles' => $userRepository->findActiveOrdered(),
            'ready_total' => $billingService->readyToInvoiceTotalHt(),
            'overdue_total' => $billingService->overdueTotalHt(),
            'forecast_maintenance' => $billingService->forecastMaintenanceRevenue(),
            'scope_limited' => null !== $scope,
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(
        Request $request,
        ContractMilestoneRepository $milestoneRepository,
        ProjectRepository $projectRepository,
        PermissionChecker $permissionChecker,
        SpreadsheetExporter $exporter,
    ): Response {
        if (!$this->isGranted('contract.view_all') && !$this->isGranted('contract.view_own')) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à exporter la facturation contractuelle.');
        }

        $scope = $permissionChecker->contractScope($this->getUser());
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'project' => $this->findByUuid($projectRepository, $request->query->getString('projet')),
            'billingStatus' => ContractBillingStatus::tryFrom($request->query->getString('statut')),
            'readyOnly' => $request->query->getBoolean('pretes') ?: null,
            'overdueOnly' => $request->query->getBoolean('retard') ?: null,
            'onlyFor' => $scope,
        ];

        $result = $milestoneRepository->searchBilling($filters, 1, 10000);

        $rows = [];
        foreach ($result['items'] as $milestone) {
            $contract = $milestone->getContract();
            $rows[] = [
                $milestone->getReference(),
                $milestone->getLabel(),
                $contract?->getReference(),
                $contract?->getClient()?->getName(),
                $milestone->getProject()?->getCode(),
                $milestone->getPlannedDate(),
                $milestone->getCompletionStatus()->label(),
                $milestone->getBillingStatus()->label(),
                $milestone->getAmountHt(),
                $milestone->getBilledAmountHt(),
                $milestone->getRemainingToInvoiceHt(),
                $milestone->isOverdue() ? 'Oui' : 'Non',
            ];
        }

        $context = [];
        if (null !== $filters['term']) {
            $context[] = 'Recherche : '.$filters['term'];
        }
        if ($filters['readyOnly']) {
            $context[] = 'Échéances prêtes à facturer';
        }
        if ($filters['overdueOnly']) {
            $context[] = 'Échéances en retard';
        }
        $context[] = $result['total'].' échéance(s)';

        return $exporter->download('facturation-a-realiser', 'Facturation à réaliser', [
            Column::text('Échéance', 18),
            Column::text('Libellé', 30),
            Column::text('Contrat', 18),
            Column::text('Client', 24),
            Column::text('Projet', 14),
            Column::date('Date prévue'),
            Column::text('Réalisation', 16),
            Column::text('Facturation', 22),
            Column::money('Prévu HT'),
            Column::money('Facturé HT'),
            Column::money('Reste HT'),
            Column::text('En retard', 10),
        ], $rows, $context);
    }

    #[Route('/{milestoneId}/action', name: 'action', methods: ['POST'], requirements: ['milestoneId' => '[0-9a-fA-F-]{36}'])]
    public function action(
        #[MapEntity(id: 'milestoneId')] ContractMilestone $milestone,
        Request $request,
        EntityManagerInterface $entityManager,
        ContractBillingService $billingService,
        ContractNotificationRepository $notificationRepository,
        InvoiceService $invoiceService,
        AuditLogger $auditLogger,
    ): Response {
        $contract = $milestone->getContract();
        if (null === $contract) {
            throw $this->createNotFoundException('Échéance sans contrat.');
        }

        if (!$this->isCsrfTokenValid('contract_billing_action_'.$milestone->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $action = $request->request->getString('action');
        $requiresBilling = \in_array($action, ['ready', 'review', 'block', 'unblock', 'invoice'], true);
        $this->denyAccessUnlessGranted($requiresBilling ? ContractVoter::BILL : ContractVoter::EDIT, $contract);

        $before = [
            'billingStatus' => $milestone->getBillingStatus()->value,
            'billedAmountHt' => $milestone->getBilledAmountHt(),
            'plannedDate' => $milestone->getPlannedDate()?->format('Y-m-d'),
        ];
        $message = 'Action sur une échéance de facturation';

        switch ($action) {
            case 'complete':
                $billingService->complete($milestone);
                $message = 'Réalisation constatée';
                break;
            case 'ready':
                $billingService->markReady($milestone);
                $message = 'Échéance prête à facturer';
                break;
            case 'review':
                $billingService->markToReview($milestone);
                $message = 'Échéance remise à vérifier';
                break;
            case 'block':
                $reason = trim($request->request->getString('reason'));
                if ('' === $reason) {
                    $this->addFlash('error', 'Un motif de blocage est obligatoire.');

                    return $this->redirectToRoute('app_contract_billing_index');
                }
                $billingService->block($milestone, $reason);
                $message = 'Échéance bloquée : '.$reason;
                break;
            case 'unblock':
                $billingService->unblock($milestone);
                $message = 'Échéance débloquée';
                break;
            case 'postpone':
                $date = $this->parseDate($request->request->getString('new_date'));
                $reason = trim($request->request->getString('reason'));
                if (null === $date || '' === $reason) {
                    $this->addFlash('error', 'La nouvelle date et le motif sont obligatoires.');

                    return $this->redirectToRoute('app_contract_billing_index');
                }
                $billingService->postpone($milestone, $date, $reason, $this->getUser());
                $notificationRepository->dismissForMilestone($milestone, 'Échéance reportée : '.$reason);
                $message = sprintf('Échéance reportée au %s', $date->format('d/m/Y'));
                break;
            case 'invoice':
                if (!\in_array($milestone->getBillingStatus(), [ContractBillingStatus::ReadyToInvoice, ContractBillingStatus::PartiallyInvoiced], true)) {
                    $this->addFlash('error', 'Une facture ne peut être créée que pour une échéance prête à facturer ou partiellement facturée.');

                    return $this->redirectToRoute('app_contract_billing_index');
                }

                $client = $contract->getClient();
                if (null === $client) {
                    $this->addFlash('error', 'Le contrat doit être rattaché à un client pour être facturé.');

                    return $this->redirectToRoute('app_contract_billing_index');
                }

                try {
                    $invoice = $invoiceService->createDraft(
                        $client,
                        $milestone->getProject(),
                        $contract,
                        $milestone,
                        $this->getUser(),
                        new \DateTimeImmutable('today'),
                    );
                    $entityManager->flush();
                } catch (\LogicException $exception) {
                    $this->addFlash('error', $exception->getMessage());

                    return $this->redirectToRoute('app_contract_billing_index');
                }

                $auditLogger->log('create', 'Invoice', $invoice->getIdString(), null, [
                    'number' => $invoice->getNumber(),
                    'milestone' => $milestone->getReference(),
                    'amountHt' => $invoice->getAmountHt(),
                ], 'success', 'M04', 'Création d’un brouillon de facture depuis une échéance contractuelle');

                $this->addFlash('success', sprintf(
                    'Brouillon de facture %s créé pour l’échéance %s. Validez la facture pour constater la facturation.',
                    $invoice->getNumber(),
                    $milestone->getReference(),
                ));

                return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
            default:
                $this->addFlash('error', 'Action inconnue.');

                return $this->redirectToRoute('app_contract_billing_index');
        }

        $entityManager->flush();

        $auditLogger->log('update', 'ContractMilestone', $milestone->getIdString(), $before, [
            'billingStatus' => $milestone->getBillingStatus()->value,
            'billedAmountHt' => $milestone->getBilledAmountHt(),
            'plannedDate' => $milestone->getPlannedDate()?->format('Y-m-d'),
        ], 'success', 'M15', $message);

        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_contract_billing_index');
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }

    private function findByUuid(object $repository, string $value): ?object
    {
        if ('' === $value || !\Symfony\Component\Uid\Uuid::isValid($value)) {
            return null;
        }

        return $repository->find($value);
    }
}
