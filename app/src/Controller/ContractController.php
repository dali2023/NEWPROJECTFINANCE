<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContractRenewalData;
use App\Entity\Contract;
use App\Entity\ContractDocument;
use App\Entity\ContractMilestone;
use App\Entity\ContractReceipt;
use App\Entity\ContractRenewal;
use App\Entity\ContractReservation;
use App\Entity\MaintenanceContract;
use App\Entity\RetentionGuarantee;
use App\Enum\CompletionStatus;
use App\Enum\ContractBillingStatus;
use App\Enum\ContractStatus;
use App\Enum\ContractType;
use App\Enum\MaintenanceRenewalStatus;
use App\Enum\ReceiptType;
use App\Enum\RenewalDecisionStatus;
use App\Form\ContractDocumentFormType;
use App\Form\ContractFormType;
use App\Form\ContractMilestoneFormType;
use App\Form\ContractReceiptFormType;
use App\Form\ContractRenewalFormType;
use App\Form\ContractReservationFormType;
use App\Form\MaintenanceContractFormType;
use App\Form\RetentionGuaranteeFormType;
use App\Repository\AuditLogRepository;
use App\Repository\ClientRepository;
use App\Repository\ContractMilestoneRepository;
use App\Repository\ContractNotificationRepository;
use App\Repository\ContractReceiptRepository;
use App\Repository\ContractRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Security\Voter\ContractVoter;
use App\Service\AuditLogger;
use App\Service\ContractBillingService;
use App\Service\InvoiceService;
use App\Service\ContractDateCalculator;
use App\Service\ContractNotificationService;
use App\Service\ContractStatusResolver;
use App\Service\DocumentStorage;
use App\Service\MaintenanceScheduleService;
use App\Service\ReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Module 15 — Contrats et maintenance (§15).
 *
 * Écrans : registre (13), fiche à neuf onglets (14), calendrier (15).
 */
#[Route('/contrats', name: 'app_contract_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ContractController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ContractRepository $contractRepository,
        ClientRepository $clientRepository,
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
        PermissionChecker $permissionChecker,
    ): Response {
        if (!$this->isGranted('contract.view_all') && !$this->isGranted('contract.view_own')) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à consulter les contrats.');
        }

        $scope = $permissionChecker->contractScope($this->getUser());
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'client' => $this->findByUuid($clientRepository, $request->query->getString('client')),
            'type' => ContractType::tryFrom($request->query->getString('type')),
            'project' => $this->findByUuid($projectRepository, $request->query->getString('projet')),
            'responsible' => $this->findByUuid($userRepository, $request->query->getString('responsable')),
            'status' => ContractStatus::tryFrom($request->query->getString('statut')),
            'expiryWithin' => $request->query->getInt('echeance') ?: null,
            'onlyFor' => $scope,
        ];

        return $this->render('contract/index.html.twig', [
            'result' => $contractRepository->search($filters, $request->query->getInt('page', 1)),
            'filters' => $filters,
            'statuses' => ContractStatus::cases(),
            'types' => ContractType::cases(),
            'clients' => $clientRepository->findActiveOrdered(),
            'projects' => $projectRepository->search([], 1, 200)['items'],
            'responsibles' => $userRepository->findActiveOrdered(),
            'scope_limited' => null !== $scope,
            'status_counts' => $contractRepository->countByStatus(),
            'total' => $contractRepository->countAll(),
            'expiring_30' => $contractRepository->countExpiringWithin(30),
            'renewals_to_prepare' => $contractRepository->countRenewalsToPrepare(),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('contract.manage')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        AuditLogger $auditLogger,
    ): Response {
        $contract = new Contract();
        $contract->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_CONTRACT))
            ->setCreatedBy($this->getUser())
            ->setResponsible($this->getUser())
            ->setCurrencyCode((string) $this->getParameter('app.currency'));

        $form = $this->createForm(ContractFormType::class, $contract);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->guardContractualStatus($form, $contract)) {
                return $this->render('contract/new.html.twig', ['form' => $form]);
            }

            $contract->recomputeTotals();
            $entityManager->persist($contract);
            $entityManager->flush();

            $auditLogger->log('create', 'Contract', $contract->getIdString(), null, $this->snapshot($contract), 'success', 'M15', 'Création d’un contrat');
            $this->addFlash('success', sprintf('Le contrat %s a été créé.', $contract->getReference()));

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString()]);
        }

        return $this->render('contract/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function show(
        Contract $contract,
        Request $request,
        AuditLogRepository $auditLogRepository,
        ContractBillingService $billingService,
        ContractDateCalculator $dateCalculator,
        ContractNotificationRepository $notificationRepository,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::VIEW, $contract);

        $tab = $request->query->getString('onglet', 'synthese');

        return $this->render('contract/show.html.twig', [
            'contract' => $contract,
            'tab' => $tab,
            'history' => $auditLogRepository->findForEntity('Contract', $contract->getIdString(), 30),
            'remaining_to_invoice' => $billingService->remainingToInvoice($contract),
            'overdue_total' => $billingService->overdueTotalHt(null, $contract),
            'forecast_maintenance' => $billingService->forecastMaintenanceRevenue(),
            'retention_remaining' => $billingService->retentionRemaining($contract),
            'next_action_date' => $dateCalculator->nextActionDate($contract),
            'indicative_final_receipt' => $dateCalculator->indicativeFinalReceiptDateForContract($contract),
            'notifications' => $notificationRepository->search(
                ['contract' => $contract, 'activeOnly' => true],
                1,
                8,
            )['items'],
            'open_reservations' => $this->countOpenReservations($contract),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function edit(
        Contract $contract,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);

        $before = $this->snapshot($contract);
        $previousStatus = $contract->getStatus();

        $form = $this->createForm(ContractFormType::class, $contract);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->guardContractualStatus($form, $contract, $previousStatus)) {
                return $this->render('contract/edit.html.twig', ['form' => $form, 'contract' => $contract]);
            }

            $contract->recomputeTotals();
            $entityManager->flush();

            $statusChanged = $previousStatus !== $contract->getStatus();
            $auditLogger->log(
                $statusChanged ? 'status_change' : 'update',
                'Contract',
                $contract->getIdString(),
                $before,
                $this->snapshot($contract),
                'success',
                'M15',
                $statusChanged
                    ? sprintf('Statut : %s → %s', $previousStatus->label(), $contract->getStatus()->label())
                    : 'Modification d’un contrat',
            );

            $this->addFlash('success', 'Le contrat a été mis à jour.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString()]);
        }

        return $this->render('contract/edit.html.twig', ['form' => $form, 'contract' => $contract]);
    }

    #[Route('/{id}/recalculer-statut', name: 'refresh_status', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function refreshStatus(
        Contract $contract,
        Request $request,
        ContractStatusResolver $statusResolver,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);

        if (!$this->isCsrfTokenValid('contract_refresh_'.$contract->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $previous = $contract->getStatus();
        $changed = $statusResolver->refresh($contract);
        if ($changed) {
            $entityManager->flush();
            $auditLogger->log(
                'status_change',
                'Contract',
                $contract->getIdString(),
                ['status' => $previous->value],
                ['status' => $contract->getStatus()->value],
                'success',
                'M15',
                'Recalcul du statut contractuel à partir des dates',
            );
            $this->addFlash('success', sprintf('Statut recalculé : %s.', $contract->getStatus()->label()));
        } else {
            $this->addFlash('info', 'Le statut est déjà à jour.');
        }

        return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'synthese']);
    }

    #[Route('/{id}/transition', name: 'transition', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function transition(
        Contract $contract,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);

        if (!$this->isCsrfTokenValid('contract_transition_'.$contract->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $action = $request->request->getString('action');
        $reason = trim($request->request->getString('reason'));
        $previous = $contract->getStatus();
        $target = match ($action) {
            'submit_signature' => ContractStatus::PendingSignature,
            'sign' => ContractStatus::Signed,
            'start' => ContractStatus::InProgress,
            'terminate' => ContractStatus::Terminated,
            'close' => ContractStatus::Closed,
            default => null,
        };

        if (null === $target) {
            $this->addFlash('error', 'Action de transition inconnue.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString()]);
        }

        if (\in_array($action, ['sign', 'terminate', 'close'], true) && !$this->isGranted('contract.validate')) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à valider cette transition.');
        }

        if (\in_array($action, ['terminate', 'close'], true) && '' === $reason) {
            $this->addFlash('error', 'Un motif est obligatoire pour cette transition.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString()]);
        }

        $contract->setStatus($target);
        $entityManager->flush();

        $auditLogger->log(
            $action,
            'Contract',
            $contract->getIdString(),
            ['status' => $previous->value],
            ['status' => $target->value, 'reason' => $reason ?: null],
            'success',
            'M15',
            sprintf('Transition contractuelle : %s → %s', $previous->label(), $target->label()),
        );

        $this->addFlash('success', sprintf('Le contrat est désormais « %s ».', $target->label()));

        return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString()]);
    }

    // -----------------------------------------------------------------
    // Maintenance
    // -----------------------------------------------------------------

    #[Route('/{id}/maintenance/nouveau', name: 'maintenance_new', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function maintenanceNew(
        Contract $contract,
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        MaintenanceScheduleService $scheduleService,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);

        $maintenance = new MaintenanceContract();
        $start = $contract->getExecutionStartDate() ?? $contract->getEffectiveStartDate() ?? new \DateTimeImmutable('today');
        $maintenance->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_MAINTENANCE))
            ->setContract($contract)
            ->setOriginalContract($contract)
            ->setMaintenanceStartDate($start)
            ->setMaintenanceEndDate($start->modify('+12 months -1 day'))
            ->setDurationMonths(12)
            ->setVatRate($contract->getVatRate())
            ->setRenewalNoticeDays($contract->getNoticePeriodDays())
            ->setCommercialResponsible($contract->getResponsible());

        $form = $this->createForm(MaintenanceContractFormType::class, $maintenance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $scheduleService->refreshAnnualAmount($maintenance);
            $contract->addMaintenanceContract($maintenance);
            $entityManager->persist($maintenance);
            $entityManager->flush();

            $auditLogger->log('create', 'MaintenanceContract', $maintenance->getIdString(), null, [
                'reference' => $maintenance->getReference(),
                'contract' => $contract->getReference(),
                'start' => $maintenance->getMaintenanceStartDate()?->format('Y-m-d'),
                'end' => $maintenance->getMaintenanceEndDate()?->format('Y-m-d'),
            ], 'success', 'M15', 'Création d’un contrat de maintenance');

            $this->addFlash('success', sprintf('Le contrat de maintenance %s a été créé.', $maintenance->getReference()));

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'maintenance']);
        }

        return $this->render('contract/maintenance/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
            'maintenance' => $maintenance,
        ]);
    }

    #[Route('/{id}/maintenance/{maintenanceId}/modifier', name: 'maintenance_edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'maintenanceId' => '[0-9a-fA-F-]{36}'])]
    public function maintenanceEdit(
        Contract $contract,
        #[MapEntity(id: 'maintenanceId')] MaintenanceContract $maintenance,
        Request $request,
        EntityManagerInterface $entityManager,
        MaintenanceScheduleService $scheduleService,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);
        $this->assertBelongsToContract($maintenance->getContract(), $contract);

        $before = [
            'reference' => $maintenance->getReference(),
            'start' => $maintenance->getMaintenanceStartDate()?->format('Y-m-d'),
            'end' => $maintenance->getMaintenanceEndDate()?->format('Y-m-d'),
            'periodicAmountHt' => $maintenance->getPeriodicAmountHt(),
            'renewalStatus' => $maintenance->getRenewalStatus()->value,
        ];

        $form = $this->createForm(MaintenanceContractFormType::class, $maintenance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $scheduleService->refreshAnnualAmount($maintenance);
            $entityManager->flush();

            $auditLogger->log('update', 'MaintenanceContract', $maintenance->getIdString(), $before, [
                'reference' => $maintenance->getReference(),
                'start' => $maintenance->getMaintenanceStartDate()?->format('Y-m-d'),
                'end' => $maintenance->getMaintenanceEndDate()?->format('Y-m-d'),
                'periodicAmountHt' => $maintenance->getPeriodicAmountHt(),
                'renewalStatus' => $maintenance->getRenewalStatus()->value,
            ], 'success', 'M15', 'Modification d’un contrat de maintenance');

            $this->addFlash('success', 'Le contrat de maintenance a été mis à jour.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'maintenance']);
        }

        return $this->render('contract/maintenance/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
            'maintenance' => $maintenance,
        ]);
    }

    #[Route('/{id}/maintenance/{maintenanceId}/generer', name: 'maintenance_generate', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'maintenanceId' => '[0-9a-fA-F-]{36}'])]
    public function maintenanceGenerate(
        Contract $contract,
        #[MapEntity(id: 'maintenanceId')] MaintenanceContract $maintenance,
        Request $request,
        MaintenanceScheduleService $scheduleService,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);
        $this->assertBelongsToContract($maintenance->getContract(), $contract);

        if (!$this->isCsrfTokenValid('maintenance_generate_'.$maintenance->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $created = $scheduleService->generate($maintenance);

        $auditLogger->log('generate', 'ContractMilestone', $maintenance->getIdString(), null, [
            'maintenance' => $maintenance->getReference(),
            'created' => \count($created),
        ], 'success', 'M15', 'Génération des échéances de maintenance');

        $this->addFlash(
            [] === $created ? 'info' : 'success',
            [] === $created
                ? 'Aucune nouvelle échéance : la période est déjà couverte.'
                : sprintf('%d échéance(s) de maintenance générée(s).', \count($created)),
        );

        return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'maintenance']);
    }

    // -----------------------------------------------------------------
    // Échéances contractuelles
    // -----------------------------------------------------------------

    #[Route('/{id}/echeances/nouveau', name: 'milestone_new', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function milestoneNew(
        Contract $contract,
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);

        $milestone = new ContractMilestone();
        $milestone->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_CONTRACT_MILESTONE))
            ->setContract($contract)
            ->setProject($contract->getProject())
            ->setVatRate($contract->getVatRate())
            ->setResponsible($contract->getResponsible())
            ->setCompletionStatus(CompletionStatus::Upcoming)
            ->setBillingStatus(ContractBillingStatus::NotBillable);

        $form = $this->createForm(ContractMilestoneFormType::class, $milestone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contract->addMilestone($milestone);
            $entityManager->persist($milestone);
            $entityManager->flush();

            $auditLogger->log('create', 'ContractMilestone', $milestone->getIdString(), null, [
                'reference' => $milestone->getReference(),
                'contract' => $contract->getReference(),
                'amountHt' => $milestone->getAmountHt(),
                'plannedDate' => $milestone->getPlannedDate()?->format('Y-m-d'),
            ], 'success', 'M15', 'Création d’une échéance contractuelle');

            $this->addFlash('success', sprintf('L’échéance %s a été créée.', $milestone->getReference()));

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
        }

        return $this->render('contract/milestone/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
            'milestone' => $milestone,
        ]);
    }

    #[Route('/{id}/echeances/{milestoneId}/modifier', name: 'milestone_edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'milestoneId' => '[0-9a-fA-F-]{36}'])]
    public function milestoneEdit(
        Contract $contract,
        #[MapEntity(id: 'milestoneId')] ContractMilestone $milestone,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);
        $this->assertBelongsToContract($milestone->getContract(), $contract);

        $before = [
            'label' => $milestone->getLabel(),
            'plannedDate' => $milestone->getPlannedDate()?->format('Y-m-d'),
            'amountHt' => $milestone->getAmountHt(),
            'completionStatus' => $milestone->getCompletionStatus()->value,
            'billingStatus' => $milestone->getBillingStatus()->value,
        ];

        $form = $this->createForm(ContractMilestoneFormType::class, $milestone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $auditLogger->log('update', 'ContractMilestone', $milestone->getIdString(), $before, [
                'label' => $milestone->getLabel(),
                'plannedDate' => $milestone->getPlannedDate()?->format('Y-m-d'),
                'amountHt' => $milestone->getAmountHt(),
                'completionStatus' => $milestone->getCompletionStatus()->value,
                'billingStatus' => $milestone->getBillingStatus()->value,
            ], 'success', 'M15', 'Modification d’une échéance contractuelle');

            $this->addFlash('success', 'L’échéance a été mise à jour.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
        }

        return $this->render('contract/milestone/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
            'milestone' => $milestone,
        ]);
    }

    #[Route('/{id}/echeances/{milestoneId}/action', name: 'milestone_action', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'milestoneId' => '[0-9a-fA-F-]{36}'])]
    public function milestoneAction(
        Contract $contract,
        #[MapEntity(id: 'milestoneId')] ContractMilestone $milestone,
        Request $request,
        EntityManagerInterface $entityManager,
        ContractBillingService $billingService,
        ContractNotificationRepository $notificationRepository,
        InvoiceService $invoiceService,
        AuditLogger $auditLogger,
    ): Response {
        $this->assertBelongsToContract($milestone->getContract(), $contract);

        if (!$this->isCsrfTokenValid('contract_milestone_action_'.$milestone->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $action = $request->request->getString('action');
        $requiresBilling = \in_array($action, ['ready', 'review', 'block', 'unblock', 'invoice'], true);
        $this->denyAccessUnlessGranted($requiresBilling ? ContractVoter::BILL : ContractVoter::EDIT, $contract);

        $before = [
            'completionStatus' => $milestone->getCompletionStatus()->value,
            'billingStatus' => $milestone->getBillingStatus()->value,
            'billedAmountHt' => $milestone->getBilledAmountHt(),
            'plannedDate' => $milestone->getPlannedDate()?->format('Y-m-d'),
        ];
        $message = 'Action sur une échéance contractuelle';

        switch ($action) {
            case 'complete':
                $billingService->complete($milestone);
                $message = 'Réalisation de l’échéance constatée';
                break;
            case 'ready':
                $billingService->markReady($milestone);
                $message = 'Échéance déclarée prête à facturer';
                break;
            case 'review':
                $billingService->markToReview($milestone);
                $message = 'Échéance remise à vérifier';
                break;
            case 'block':
                $reason = trim($request->request->getString('reason'));
                if ('' === $reason) {
                    $this->addFlash('error', 'Un motif de blocage est obligatoire.');

                    return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
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
                    $this->addFlash('error', 'La nouvelle date et le motif du report sont obligatoires.');

                    return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
                }
                $billingService->postpone($milestone, $date, $reason, $this->getUser());
                $notificationRepository->dismissForMilestone($milestone, 'Échéance reportée : '.$reason);
                $message = sprintf('Échéance reportée au %s : %s', $date->format('d/m/Y'), $reason);
                break;
            case 'invoice':
                if (!\in_array($milestone->getBillingStatus(), [ContractBillingStatus::ReadyToInvoice, ContractBillingStatus::PartiallyInvoiced], true)) {
                    $this->addFlash('error', 'Une facture ne peut être créée que pour une échéance prête à facturer ou partiellement facturée.');

                    return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
                }

                $client = $contract->getClient();
                if (null === $client) {
                    $this->addFlash('error', 'Le contrat doit être rattaché à un client pour être facturé.');

                    return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
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

                    return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
                }

                $auditLogger->log('create', 'Invoice', $invoice->getIdString(), null, [
                    'number' => $invoice->getNumber(),
                    'milestone' => $milestone->getReference(),
                    'amountHt' => $invoice->getAmountHt(),
                ], 'success', 'M04', 'Création d’un brouillon de facture depuis une échéance contractuelle');

                $this->addFlash('success', sprintf(
                    'Brouillon de facture %s créé. Validez la facture pour constater la facturation.',
                    $invoice->getNumber(),
                ));

                return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
            default:
                $this->addFlash('error', 'Action inconnue.');

                return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
        }

        $entityManager->flush();

        $auditLogger->log('update', 'ContractMilestone', $milestone->getIdString(), $before, [
            'completionStatus' => $milestone->getCompletionStatus()->value,
            'billingStatus' => $milestone->getBillingStatus()->value,
            'billedAmountHt' => $milestone->getBilledAmountHt(),
            'plannedDate' => $milestone->getPlannedDate()?->format('Y-m-d'),
        ], 'success', 'M15', $message);

        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'echeances']);
    }

    // -----------------------------------------------------------------
    // Réceptions, réserves, retenues
    // -----------------------------------------------------------------

    #[Route('/{id}/receptions/nouvelle', name: 'receipt_new', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function receiptNew(
        Contract $contract,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);

        $receipt = new ContractReceipt();
        $receipt->setContract($contract)->setProject($contract->getProject());

        $form = $this->createForm(ContractReceiptFormType::class, $receipt);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contract->addReceipt($receipt);
            $entityManager->persist($receipt);
            $entityManager->flush();

            $auditLogger->log('create', 'ContractReceipt', $receipt->getIdString(), null, [
                'type' => $receipt->getReceiptType()->value,
                'contract' => $contract->getReference(),
                'actualDate' => $receipt->getActualDate()?->format('Y-m-d'),
                'pvReference' => $receipt->getPvReference(),
            ], 'success', 'M15', 'Création d’une réception contractuelle');

            $this->addFlash('success', 'La réception a été enregistrée. Elle reste à valider si elle est effective.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'receptions']);
        }

        return $this->render('contract/receipt/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
            'receipt' => $receipt,
        ]);
    }

    #[Route('/{id}/receptions/{receiptId}/modifier', name: 'receipt_edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'receiptId' => '[0-9a-fA-F-]{36}'])]
    public function receiptEdit(
        Contract $contract,
        #[MapEntity(id: 'receiptId')] ContractReceipt $receipt,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);
        $this->assertBelongsToContract($receipt->getContract(), $contract);

        $form = $this->createForm(ContractReceiptFormType::class, $receipt);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $auditLogger->log('update', 'ContractReceipt', $receipt->getIdString(), null, [
                'type' => $receipt->getReceiptType()->value,
                'actualDate' => $receipt->getActualDate()?->format('Y-m-d'),
                'pvReference' => $receipt->getPvReference(),
            ], 'success', 'M15', 'Modification d’une réception contractuelle');

            $this->addFlash('success', 'La réception a été mise à jour.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'receptions']);
        }

        return $this->render('contract/receipt/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
            'receipt' => $receipt,
        ]);
    }

    #[Route('/{id}/receptions/{receiptId}/valider', name: 'receipt_validate', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'receiptId' => '[0-9a-fA-F-]{36}'])]
    public function receiptValidate(
        Contract $contract,
        #[MapEntity(id: 'receiptId')] ContractReceipt $receipt,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::VALIDATE, $contract);
        $this->assertBelongsToContract($receipt->getContract(), $contract);

        if (!$this->isCsrfTokenValid('contract_receipt_validate_'.$receipt->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (null === $receipt->getActualDate() || null === $receipt->getPvReference() || '' === trim($receipt->getPvReference())) {
            $this->addFlash('error', 'La date effective et la référence du procès-verbal sont nécessaires pour valider une réception.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'receptions']);
        }

        if ($receipt->isFinal()) {
            if (null === $receipt->getWarrantyStartDate()) {
                $receipt->setWarrantyStartDate($receipt->getActualDate());
            }
            if (null === $receipt->getWarrantyEndDate()) {
                $receipt->setWarrantyEndDate($receipt->getActualDate()->modify(sprintf('+%d days', $contract->getWarrantyDurationDays())));
            }
        }

        $receipt->validate($this->getUser());
        $entityManager->flush();

        $auditLogger->log('validate', 'ContractReceipt', $receipt->getIdString(), null, [
            'type' => $receipt->getReceiptType()->value,
            'validatedAt' => $receipt->getValidatedAt()?->format(\DateTimeInterface::ATOM),
        ], 'success', 'M15', 'Validation d’une réception contractuelle');

        $this->addFlash('success', 'La réception a été validée.');

        return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'receptions']);
    }

    #[Route('/{id}/receptions/{receiptId}/reserves/nouvelle', name: 'reservation_new', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'receiptId' => '[0-9a-fA-F-]{36}'])]
    public function reservationNew(
        Contract $contract,
        #[MapEntity(id: 'receiptId')] ContractReceipt $receipt,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);
        $this->assertBelongsToContract($receipt->getContract(), $contract);

        $reservation = new ContractReservation();
        $reservation->setReceipt($receipt)->setResponsible($contract->getResponsible());

        $form = $this->createForm(ContractReservationFormType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $receipt->addReservation($reservation);
            $entityManager->persist($reservation);
            $entityManager->flush();

            $auditLogger->log('create', 'ContractReservation', $reservation->getIdString(), null, [
                'receipt' => $receipt->getIdString(),
                'description' => $reservation->getDescription(),
            ], 'success', 'M15', 'Constat d’une réserve');

            $this->addFlash('success', 'La réserve a été enregistrée.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'receptions']);
        }

        return $this->render('contract/reservation/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
            'receipt' => $receipt,
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/reserves/{reservationId}/lever', name: 'reservation_lift', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'reservationId' => '[0-9a-fA-F-]{36}'])]
    public function reservationLift(
        Contract $contract,
        #[MapEntity(id: 'reservationId')] ContractReservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);
        $this->assertBelongsToContract($reservation->getReceipt()?->getContract(), $contract);

        if (!$this->isCsrfTokenValid('contract_reservation_lift_'.$reservation->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$reservation->isOpen()) {
            $this->addFlash('info', 'Cette réserve est déjà levée ou annulée.');
        } else {
            $reservation->lift($this->parseDate($request->request->getString('date')));
            $entityManager->flush();
            $auditLogger->log('update', 'ContractReservation', $reservation->getIdString(), null, [
                'status' => $reservation->getStatus()->value,
                'actualResolutionDate' => $reservation->getActualResolutionDate()?->format('Y-m-d'),
            ], 'success', 'M15', 'Levée d’une réserve');
            $this->addFlash('success', 'La réserve a été levée.');
        }

        return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'receptions']);
    }

    #[Route('/{id}/retenues/nouvelle', name: 'retention_new', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function retentionNew(
        Contract $contract,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);

        $retention = new RetentionGuarantee();
        $retention->setContract($contract)->setProject($contract->getProject())->setCurrencyCode($contract->getCurrencyCode());

        $form = $this->createForm(RetentionGuaranteeFormType::class, $retention);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contract->addRetentionGuarantee($retention);
            $entityManager->persist($retention);
            $entityManager->flush();

            $auditLogger->log('create', 'RetentionGuarantee', $retention->getIdString(), null, [
                'contract' => $contract->getReference(),
                'amount' => $retention->getAmount(),
                'eligibilityDate' => $retention->getEligibilityDate()?->format('Y-m-d'),
            ], 'success', 'M15', 'Création d’une retenue de garantie');

            $this->addFlash('success', 'La retenue de garantie a été enregistrée.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'retenues']);
        }

        return $this->render('contract/retention/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
            'retention' => $retention,
        ]);
    }

    #[Route('/{id}/retenues/{retentionId}/liberer', name: 'retention_release', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'retentionId' => '[0-9a-fA-F-]{36}'])]
    public function retentionRelease(
        Contract $contract,
        #[MapEntity(id: 'retentionId')] RetentionGuarantee $retention,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::BILL, $contract);
        $this->assertBelongsToContract($retention->getContract(), $contract);

        if (!$this->isCsrfTokenValid('contract_retention_release_'.$retention->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $amount = $request->request->getString('amount');
        if ('' === trim($amount)) {
            $this->addFlash('error', 'Le montant libéré est obligatoire.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'retenues']);
        }

        $retention->release($amount, $this->parseDate($request->request->getString('date')));
        $entityManager->flush();

        $auditLogger->log('release', 'RetentionGuarantee', $retention->getIdString(), null, [
            'releasedAmount' => $retention->getReleasedAmount(),
            'status' => $retention->getStatus()->value,
        ], 'success', 'M15', 'Libération d’une retenue de garantie');

        $this->addFlash('success', 'La libération de la retenue a été enregistrée.');

        return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'retenues']);
    }

    // -----------------------------------------------------------------
    // Renouvellement explicite
    // -----------------------------------------------------------------

    #[Route('/{id}/renouvellement', name: 'renewal_new', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function renewalNew(
        Contract $contract,
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        MaintenanceScheduleService $scheduleService,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::RENEW, $contract);

        $data = new ContractRenewalData();
        $data->newStartDate = $contract->getEffectiveEndDate()?->modify('+1 day');
        $data->newEndDate = $data->newStartDate?->modify('+12 months -1 day');
        $data->newAmountHt = $contract->getAmountHt();
        $data->responsible = $contract->getResponsible();

        $form = $this->createForm(ContractRenewalFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $renewal = new ContractRenewal();
            $renewal->setContract($contract)
                ->setPreviousContract($contract)
                ->setRenewalDeadline($contract->getRenewalPreparationDate())
                ->setDecisionDate(new \DateTimeImmutable('today'))
                ->setDecisionStatus($data->decisionStatus)
                ->setRenewalAmountHt($data->newAmountHt)
                ->setResponsible($data->responsible ?? $this->getUser())
                ->setNotes($data->notes);

            $newContract = null;

            if (RenewalDecisionStatus::Accepted === $data->decisionStatus) {
                if (null === $data->newStartDate || null === $data->newEndDate || $data->newEndDate < $data->newStartDate) {
                    $form->addError(new FormError('Les nouvelles dates de début et de fin sont obligatoires et cohérentes.'));

                    return $this->render('contract/renewal/form.html.twig', [
                        'form' => $form,
                        'contract' => $contract,
                    ]);
                }

                $newContract = $this->cloneForRenewal($contract, $data, $referenceGenerator);
                $entityManager->persist($newContract);
                $renewal->setNewContract($newContract);

                foreach ($contract->getMaintenanceContracts() as $maintenance) {
                    $newMaintenance = $this->cloneMaintenance($maintenance, $newContract, $data, $referenceGenerator);
                    $newContract->addMaintenanceContract($newMaintenance);
                    $entityManager->persist($newMaintenance);
                    if ($data->generateMilestones) {
                        $scheduleService->generate($newMaintenance, false);
                    }
                }

                $contract->setStatus(ContractStatus::Closed);
            } else {
                foreach ($contract->getMaintenanceContracts() as $maintenance) {
                    if (\in_array($data->decisionStatus, [RenewalDecisionStatus::Contacted, RenewalDecisionStatus::Negotiating], true)) {
                        $maintenance->setRenewalStatus(MaintenanceRenewalStatus::InNegotiation);
                    }
                }
            }

            $contract->addRenewal($renewal);
            $entityManager->persist($renewal);
            $entityManager->flush();

            $auditLogger->log('renew', 'Contract', $contract->getIdString(), null, [
                'decision' => $data->decisionStatus->value,
                'newContract' => $newContract?->getReference(),
                'newStart' => $data->newStartDate?->format('Y-m-d'),
                'newEnd' => $data->newEndDate?->format('Y-m-d'),
                'amountHt' => $data->newAmountHt,
            ], 'success', 'M15', 'Enregistrement d’une décision de renouvellement');

            $this->addFlash('success', RenewalDecisionStatus::Accepted === $data->decisionStatus
                ? sprintf('Le contrat a été renouvelé : %s.', $newContract?->getReference())
                : 'La décision de renouvellement a été enregistrée.');

            return $this->redirectToRoute('app_contract_show', [
                'id' => ($newContract ?? $contract)->getIdString(),
                'onglet' => 'renouvellements',
            ]);
        }

        return $this->render('contract/renewal/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
        ]);
    }

    // -----------------------------------------------------------------
    // Documents
    // -----------------------------------------------------------------

    #[Route('/{id}/documents/nouveau', name: 'document_new', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function documentNew(
        Contract $contract,
        Request $request,
        EntityManagerInterface $entityManager,
        DocumentStorage $documentStorage,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::EDIT, $contract);

        $document = new ContractDocument();
        $form = $this->createForm(ContractDocumentFormType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();
            if (null === $file) {
                $form->addError(new FormError('Le fichier est obligatoire.'));

                return $this->render('contract/document/form.html.twig', [
                    'form' => $form,
                    'contract' => $contract,
                ]);
            }

            $path = $documentStorage->store($file, 'contracts/'.$contract->getIdString());
            $document->setFilePath($path)
                ->setMimeType($file->getClientMimeType())
                ->setFileSize($file->getSize() ?: null)
                ->setUploadedBy($this->getUser());
            $contract->addDocument($document);
            if (null === $contract->getMainDocument()) {
                $contract->setMainDocument($document);
            }
            $entityManager->persist($document);
            $entityManager->flush();

            $auditLogger->log('upload', 'ContractDocument', $document->getIdString(), null, [
                'label' => $document->getLabel(),
                'type' => $document->getDocumentType()->value,
                'contract' => $contract->getReference(),
            ], 'success', 'M15', 'Dépôt d’un document contractuel');

            $this->addFlash('success', 'Le document a été déposé.');

            return $this->redirectToRoute('app_contract_show', ['id' => $contract->getIdString(), 'onglet' => 'documents']);
        }

        return $this->render('contract/document/form.html.twig', [
            'form' => $form,
            'contract' => $contract,
        ]);
    }

    #[Route('/{id}/documents/{documentId}/telecharger', name: 'document_download', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'documentId' => '[0-9a-fA-F-]{36}'])]
    public function documentDownload(
        Contract $contract,
        #[MapEntity(id: 'documentId')] ContractDocument $document,
        DocumentStorage $documentStorage,
    ): Response {
        $this->denyAccessUnlessGranted(ContractVoter::VIEW, $contract);
        $this->assertBelongsToContract($document->getContract(), $contract);

        if (!$documentStorage->exists($document->getFilePath())) {
            throw $this->createNotFoundException('Le fichier demandé est introuvable.');
        }

        return $this->file($documentStorage->absolutePath($document->getFilePath()), $document->getLabel());
    }

    private function guardContractualStatus(FormInterface $form, Contract $contract, ?ContractStatus $previous = null): bool
    {
        if (!$contract->getStatus()->isContractual()) {
            return true;
        }

        if (null !== $previous && $previous === $contract->getStatus()) {
            return true;
        }

        if ($this->isGranted('contract.validate')) {
            return true;
        }

        $form->addError(new FormError('Seul un utilisateur habilité peut valider un statut contractuel.'));

        return false;
    }

    private function cloneForRenewal(Contract $source, ContractRenewalData $data, ReferenceGenerator $referenceGenerator): Contract
    {
        $new = new Contract();
        $new->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_CONTRACT))
            ->setTitle($source->getTitle())
            ->setContractType($source->getContractType())
            ->setClient($source->getClient())
            ->setProject($source->getProject())
            ->setMarketReference($source->getMarketReference())
            ->setSignatureDate(new \DateTimeImmutable('today'))
            ->setEffectiveStartDate($data->newStartDate)
            ->setExecutionStartDate($data->newStartDate)
            ->setEffectiveEndDate($data->newEndDate)
            ->setAmountHt($data->newAmountHt)
            ->setVatRate($source->getVatRate())
            ->setCurrencyCode($source->getCurrencyCode())
            ->setResponsible($data->responsible ?? $source->getResponsible())
            ->setPaymentTerms($source->getPaymentTerms())
            ->setRenewalTerms($source->getRenewalTerms())
            ->setNoticePeriodDays($source->getNoticePeriodDays())
            ->setRenewalMode($source->getRenewalMode())
            ->setWarrantyDurationDays($source->getWarrantyDurationDays())
            ->setStatus(ContractStatus::Signed)
            ->setCreatedBy($this->getUser())
            ->setNotes(sprintf('Contrat renouvelé depuis %s.%s', $source->getReference(), $data->notes ? ' '.$data->notes : ''));

        return $new;
    }

    private function cloneMaintenance(
        MaintenanceContract $source,
        Contract $newContract,
        ContractRenewalData $data,
        ReferenceGenerator $referenceGenerator,
    ): MaintenanceContract {
        $maintenance = new MaintenanceContract();
        $maintenance->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_MAINTENANCE))
            ->setContract($newContract)
            ->setOriginalContract($source->getContract())
            ->setMaintenanceStartDate($data->newStartDate)
            ->setMaintenanceEndDate($data->newEndDate)
            ->setDurationMonths($source->getDurationMonths())
            ->setBillingFrequency($source->getBillingFrequency())
            ->setPeriodicAmountHt($source->getPeriodicAmountHt())
            ->setAnnualAmountHt($source->getAnnualAmountHt())
            ->setVatRate($source->getVatRate())
            ->setPriceRevisionTerms($source->getPriceRevisionTerms())
            ->setRenewalNoticeDays($source->getRenewalNoticeDays())
            ->setRenewalMode($source->getRenewalMode())
            ->setRenewalStatus(MaintenanceRenewalStatus::NotDue)
            ->setCommercialResponsible($source->getCommercialResponsible())
            ->setTechnicalResponsible($source->getTechnicalResponsible())
            ->setNotes('Maintenance reprise lors du renouvellement de '.$source->getReference().'.');

        return $maintenance;
    }

    private function countOpenReservations(Contract $contract): int
    {
        $count = 0;
        foreach ($contract->getReceipts() as $receipt) {
            $count += $receipt->getOpenReservationsCount();
        }

        return $count;
    }

    private function assertBelongsToContract(?Contract $actual, Contract $expected): void
    {
        if (null === $actual || $actual->getIdString() !== $expected->getIdString()) {
            throw $this->createNotFoundException('Cet élément n’appartient pas au contrat demandé.');
        }
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
        if ('' === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $repository->find($value);
    }

    /** @return array<string, mixed> */
    private function snapshot(Contract $contract): array
    {
        return [
            'reference' => $contract->getReference(),
            'title' => $contract->getTitle(),
            'status' => $contract->getStatus()->value,
            'client' => $contract->getClient()?->getName(),
            'type' => $contract->getContractType()->value,
            'amountHt' => $contract->getAmountHt(),
            'effectiveEndDate' => $contract->getEffectiveEndDate()?->format('Y-m-d'),
        ];
    }
}
