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
