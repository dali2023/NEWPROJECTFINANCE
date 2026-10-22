<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContractNotification;
use App\Entity\User;
use App\Enum\ContractNotificationSeverity;
use App\Enum\ContractNotificationStatus;
use App\Enum\ContractNotificationType;
use App\Repository\AuditLogRepository;
use App\Repository\ContractNotificationRepository;
use App\Repository\ContractRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Security\Voter\ContractVoter;
use App\Service\AuditLogger;
use App\Service\ContractNotificationService;
use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use App\Service\Mail\ContractReminderMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran 17 — centre de notifications contractuelles (§15.7, §15.8).
 *
 * Aucune alerte n'est dupliquée pour un même événement, un même délai et un
 * même destinataire. La clôture d'une alerte exige un motif.
 */
#[Route('/contrats/notifications', name: 'app_contract_notification_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ContractNotificationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ContractNotificationRepository $notificationRepository,
        ContractRepository $contractRepository,
        UserRepository $userRepository,
        PermissionChecker $permissionChecker,
    ): Response {
        if (!$this->isGranted('contract.view_all') && !$this->isGranted('contract.view_own') && !$this->isGranted('contract.notify')) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à consulter les notifications contractuelles.');
        }

        $scope = $permissionChecker->contractScope($this->getUser());
        $contract = null;
        $contractId = $request->query->getString('contrat');
        if ('' !== $contractId && \Symfony\Component\Uid\Uuid::isValid($contractId)) {
            $contract = $contractRepository->find($contractId);
        }

        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'type' => ContractNotificationType::tryFrom($request->query->getString('type')),
            'status' => ContractNotificationStatus::tryFrom($request->query->getString('statut')),
            'severity' => ContractNotificationSeverity::tryFrom($request->query->getString('gravite')),
            'recipient' => $this->findUser($userRepository, $request->query->getString('destinataire')),
            'contract' => $contract,
            'activeOnly' => !$request->query->getBoolean('historique'),
            'scopeUser' => null === $contract ? $scope : null,
        ];

        return $this->render('contract/notification/index.html.twig', [
            'result' => $notificationRepository->search($filters, $request->query->getInt('page', 1)),
            'filters' => $filters,
            'types' => ContractNotificationType::cases(),
            'statuses' => ContractNotificationStatus::cases(),
            'severities' => ContractNotificationSeverity::cases(),
            'recipients' => $userRepository->findActiveOrdered(),
            'contract' => $contract,
            'active_count' => $notificationRepository->countActive($scope),
            'scope_limited' => null !== $scope,
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(
        Request $request,
        ContractNotificationRepository $notificationRepository,
        ContractRepository $contractRepository,
        UserRepository $userRepository,
        PermissionChecker $permissionChecker,
        SpreadsheetExporter $exporter,
    ): Response {
        if (!$this->isGranted('contract.view_all') && !$this->isGranted('contract.view_own') && !$this->isGranted('contract.notify')) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à exporter les notifications contractuelles.');
        }

        $scope = $permissionChecker->contractScope($this->getUser());
        $contract = null;
        $contractId = $request->query->getString('contrat');
        if ('' !== $contractId && \Symfony\Component\Uid\Uuid::isValid($contractId)) {
            $contract = $contractRepository->find($contractId);
        }

        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'type' => ContractNotificationType::tryFrom($request->query->getString('type')),
            'status' => ContractNotificationStatus::tryFrom($request->query->getString('statut')),
            'severity' => ContractNotificationSeverity::tryFrom($request->query->getString('gravite')),
            'recipient' => $this->findUser($userRepository, $request->query->getString('destinataire')),
            'contract' => $contract,
            'activeOnly' => !$request->query->getBoolean('historique'),
            'scopeUser' => null === $contract ? $scope : null,
        ];

        $result = $notificationRepository->search($filters, 1, 10000);

        $rows = [];
        foreach ($result['items'] as $notification) {
            $rows[] = [
                $notification->getSeverity()->label(),
                $notification->getNotificationType()->label(),
                $notification->getTitle(),
                $notification->getContract()?->getReference(),
                $notification->getContract()?->getClient()?->getName(),
                $notification->getEventDate(),
                $notification->getTriggerDate(),
                $notification->getAlertDelayDays(),
                $notification->getStatus()->label(),
                $notification->getRecipient()?->getFullName(),
            ];
        }

        $context = [];
        if ($filters['activeOnly']) {
            $context[] = 'Alertes actives seulement';
        }
        if (null !== $filters['term']) {
            $context[] = 'Recherche : '.$filters['term'];
        }
        $context[] = $result['total'].' notification(s)';

        return $exporter->download('notifications-contractuelles', 'Centre de notifications contractuelles', [
            Column::text('Gravité', 12),
            Column::text('Événement', 28),
            Column::text('Titre', 34),
            Column::text('Contrat', 18),
            Column::text('Client', 24),
            Column::date('Date déclencheuse'),
            Column::date('Déclenchement'),
            Column::integer('Délai (jours)'),
            Column::text('Statut', 14),
            Column::text('Destinataire', 22),
        ], $rows, $context);
    }

    #[Route('/generer', name: 'generate', methods: ['POST'])]
    #[IsGranted('contract.notify')]
    public function generate(
        Request $request,
        ContractNotificationService $notificationService,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('contract_notifications_generate', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $created = $notificationService->generateAll();

        $auditLogger->log('generate', 'ContractNotification', null, null, [
            'created' => $created,
        ], 'success', 'M15', 'Génération des alertes contractuelles');

        $this->addFlash('success', 0 === $created
            ? 'Aucune nouvelle alerte : toutes les échéances sont déjà couvertes.'
            : sprintf('%d nouvelle(s) alerte(s) générée(s).', $created));

        return $this->redirectToRoute('app_contract_notification_index');
    }

    #[Route('/envoyer-rappels', name: 'send_reminders', methods: ['POST'])]
    #[IsGranted('contract.notify')]
    public function sendReminders(
        Request $request,
        ContractNotificationService $notificationService,
        ContractReminderMailer $reminderMailer,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('contract_notifications_send_reminders', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $created = $notificationService->generateAll();
        $result = $reminderMailer->sendPending();

        if (isset($result['reason'])) {
            $auditLogger->log('send_reminders', 'ContractNotification', null, null, [
                'created' => $created,
                'reason' => $result['reason'],
            ], 'failure', 'M15', 'Envoi des rappels contractuels refusé');

            $this->addFlash('error', $result['reason']);

            return $this->redirectToRoute('app_contract_notification_index');
        }

        $auditLogger->log('send_reminders', 'ContractNotification', null, null, [
            'created' => $created,
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
        ], 'success', 'M15', 'Envoi des rappels contractuels par email');

        if (0 === $result['sent'] && 0 === $result['failed']) {
            $this->addFlash('info', sprintf(
                'Aucun rappel à envoyer : %d alerte(s) générée(s), aucune alerte en attente d’email.',
                $created,
            ));
        } else {
            $message = sprintf('%d rappel(s) envoyé(s)', $result['sent']);
            if ($result['failed'] > 0) {
                $message .= sprintf(', %d échec(s) — consultez le détail sur les alertes', $result['failed']);
            }
            if ($result['skipped'] > 0) {
                $message .= sprintf(', %d ignoré(s) faute d’adresse destinataire', $result['skipped']);
            }
            $this->addFlash($result['failed'] > 0 ? 'warning' : 'success', $message.'.');
        }

        return $this->redirectToRoute('app_contract_notification_index');
    }

    #[Route('/{notificationId}/prendre-en-charge', name: 'acknowledge', methods: ['POST'], requirements: ['notificationId' => '[0-9a-fA-F-]{36}'])]
    public function acknowledge(
        #[MapEntity(id: 'notificationId')] ContractNotification $notification,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $contract = $notification->getContract();
        if (null === $contract) {
            throw $this->createNotFoundException('Notification sans contrat.');
        }
        $this->denyAccessUnlessGranted(ContractVoter::NOTIFY, $contract);

        if (!$this->isCsrfTokenValid('contract_notification_ack_'.$notification->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $notification->acknowledge($this->getUser() instanceof User ? $this->getUser() : null);
        $entityManager->flush();

        $auditLogger->log('acknowledge', 'ContractNotification', $notification->getIdString(), null, [
            'status' => $notification->getStatus()->value,
        ], 'success', 'M15', 'Prise en charge d’une alerte contractuelle');

        $this->addFlash('success', 'L’alerte a été prise en charge.');

        return $this->redirectToRoute('app_contract_notification_index');
    }

    #[Route('/{notificationId}/resoudre', name: 'resolve', methods: ['POST'], requirements: ['notificationId' => '[0-9a-fA-F-]{36}'])]
    public function resolve(
        #[MapEntity(id: 'notificationId')] ContractNotification $notification,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $contract = $notification->getContract();
        if (null === $contract) {
            throw $this->createNotFoundException('Notification sans contrat.');
        }
        $this->denyAccessUnlessGranted(ContractVoter::NOTIFY, $contract);

        if (!$this->isCsrfTokenValid('contract_notification_resolve_'.$notification->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $note = trim($request->request->getString('note'));
        if ('' === $note) {
            $this->addFlash('error', 'Un motif est obligatoire pour clôturer une alerte.');

            return $this->redirectToRoute('app_contract_notification_index');
        }

        $notification->resolve($note);
        $entityManager->flush();

        $auditLogger->log('resolve', 'ContractNotification', $notification->getIdString(), null, [
            'status' => $notification->getStatus()->value,
            'note' => $note,
        ], 'success', 'M15', 'Résolution d’une alerte contractuelle');

        $this->addFlash('success', 'L’alerte a été clôturée.');

        return $this->redirectToRoute('app_contract_notification_index');
    }

    #[Route('/{notificationId}/sans-objet', name: 'dismiss', methods: ['POST'], requirements: ['notificationId' => '[0-9a-fA-F-]{36}'])]
    public function dismiss(
        #[MapEntity(id: 'notificationId')] ContractNotification $notification,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $contract = $notification->getContract();
        if (null === $contract) {
            throw $this->createNotFoundException('Notification sans contrat.');
        }
        $this->denyAccessUnlessGranted(ContractVoter::NOTIFY, $contract);

        if (!$this->isCsrfTokenValid('contract_notification_dismiss_'.$notification->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $reason = trim($request->request->getString('reason'));
        if ('' === $reason) {
            $this->addFlash('error', 'Un motif est obligatoire pour déclarer une alerte sans objet.');

            return $this->redirectToRoute('app_contract_notification_index');
        }

        $notification->dismiss($reason);
        $entityManager->flush();

        $auditLogger->log('dismiss', 'ContractNotification', $notification->getIdString(), null, [
            'status' => $notification->getStatus()->value,
            'reason' => $reason,
        ], 'success', 'M15', 'Alerte contractuelle déclarée sans objet');

        $this->addFlash('success', 'L’alerte a été déclarée sans objet.');

        return $this->redirectToRoute('app_contract_notification_index');
    }

    private function findUser(UserRepository $repository, string $value): ?User
    {
        if ('' === $value || !\Symfony\Component\Uid\Uuid::isValid($value)) {
            return null;
        }

        return $repository->find($value);
    }
}
