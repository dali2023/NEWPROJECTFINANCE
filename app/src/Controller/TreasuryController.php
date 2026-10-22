<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BankAccount;
use App\Entity\CashMovement;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use App\Form\BankAccountFormType;
use App\Form\CashMovementFormType;
use App\Repository\BankAccountRepository;
use App\Repository\CashMovementRepository;
use App\Service\AuditLogger;
use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use App\Service\ReferenceGenerator;
use App\Service\TreasuryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/** M09 — Trésorerie : comptes, mouvements et rapprochement. */
#[Route('/tresorerie', name: 'app_treasury_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TreasuryController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('treasury.view')]
    public function index(
        Request $request,
        BankAccountRepository $accountRepository,
        CashMovementRepository $movementRepository,
        TreasuryService $treasuryService,
    ): Response {
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'account' => $this->findByUuid($accountRepository, $request->query->getString('compte')),
            'direction' => TreasuryDirection::tryFrom($request->query->getString('sens')),
            'type' => TreasuryMovementType::tryFrom($request->query->getString('origine')),
        ];

        $from = new \DateTimeImmutable('first day of this month');
        $to = new \DateTimeImmutable('last day of this month');

        return $this->render('treasury/index.html.twig', [
            'accounts' => $accountRepository->findBy([], ['name' => 'ASC']),
            'result' => $movementRepository->search($filters, $request->query->getInt('page', 1)),
            'filters' => $filters,
            'directions' => TreasuryDirection::cases(),
            'types' => TreasuryMovementType::cases(),
            'total_balance' => $treasuryService->totalBalance(),
            'month_inflows' => $treasuryService->inflows($from, $to),
            'month_outflows' => $treasuryService->outflows($from, $to),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    #[IsGranted('treasury.view')]
    public function export(
        Request $request,
        BankAccountRepository $accountRepository,
        CashMovementRepository $movementRepository,
        SpreadsheetExporter $exporter,
    ): Response {
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'account' => $this->findByUuid($accountRepository, $request->query->getString('compte')),
            'direction' => TreasuryDirection::tryFrom($request->query->getString('sens')),
            'type' => TreasuryMovementType::tryFrom($request->query->getString('origine')),
        ];

        $result = $movementRepository->search($filters, 1, 10000);

        $rows = [];
        foreach ($result['items'] as $movement) {
            $rows[] = [
                $movement->getReference(),
                $movement->getMovementDate(),
                $movement->getBankAccount()?->getName(),
                $movement->getDirection()->label(),
                $movement->getMovementType()->label(),
                $movement->getLabel(),
                $movement->getAmount(),
                $movement->isReconciled() ? 'Oui' : 'Non',
                $movement->getReconciledAt(),
                $movement->getSourceType(),
            ];
        }

        $context = [];
        if (null !== $filters['term']) {
            $context[] = 'Recherche : '.$filters['term'];
        }
        if (null !== $filters['account']) {
            $context[] = 'Compte : '.$filters['account']->getName();
        }
        if (null !== $filters['direction']) {
            $context[] = 'Sens : '.$filters['direction']->label();
        }
        if (null !== $filters['type']) {
            $context[] = 'Origine : '.$filters['type']->label();
        }
        $context[] = $result['total'].' mouvement(s)';

        return $exporter->download('mouvements-tresorerie', 'Mouvements de trésorerie', [
            Column::text('Référence', 18),
            Column::date('Date'),
            Column::text('Compte', 24),
            Column::text('Sens', 12),
            Column::text('Origine', 20),
            Column::text('Libellé', 32),
            Column::money('Montant', 15, false),
            Column::text('Rapproché', 10),
            Column::dateTime('Rapprochement'),
            Column::text('Source', 16),
        ], $rows, $context);
    }

    #[Route('/comptes/nouveau', name: 'account_new', methods: ['GET', 'POST'])]
    #[IsGranted('treasury.manage')]
    public function accountNew(
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $account = new BankAccount();
        $account->setCreatedBy($this->getUser())
            ->setCurrencyCode((string) $this->getParameter('app.currency'))
            ->setOpeningDate(new \DateTimeImmutable('today'));

        $form = $this->createForm(BankAccountFormType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($account);
            $entityManager->flush();
            $auditLogger->log('create', 'BankAccount', $account->getIdString(), null, ['name' => $account->getName()], 'success', 'M09', 'Création d’un compte de trésorerie');
            $this->addFlash('success', 'Le compte a été créé.');

            return $this->redirectToRoute('app_treasury_account_show', ['id' => $account->getIdString()]);
        }

        return $this->render('treasury/account_form.html.twig', ['form' => $form, 'account' => $account, 'is_new' => true]);
    }

    #[Route('/comptes/{id}', name: 'account_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('treasury.view')]
    public function accountShow(BankAccount $account): Response
    {
        return $this->render('treasury/account_show.html.twig', ['account' => $account]);
    }

    #[Route('/comptes/{id}/modifier', name: 'account_edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('treasury.manage')]
    public function accountEdit(
        BankAccount $account,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $before = ['name' => $account->getName(), 'openingBalance' => $account->getOpeningBalance()];
        $form = $this->createForm(BankAccountFormType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $auditLogger->log('update', 'BankAccount', $account->getIdString(), $before, ['name' => $account->getName()], 'success', 'M09', 'Modification d’un compte de trésorerie');
            $this->addFlash('success', 'Le compte a été mis à jour.');

            return $this->redirectToRoute('app_treasury_account_show', ['id' => $account->getIdString()]);
        }

        return $this->render('treasury/account_form.html.twig', ['form' => $form, 'account' => $account, 'is_new' => false]);
    }

    #[Route('/mouvements/nouveau', name: 'movement_new', methods: ['GET', 'POST'])]
    #[IsGranted('treasury.manage')]
    public function movementNew(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        AuditLogger $auditLogger,
    ): Response {
        $movement = new CashMovement();
        $movement->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_TRANSFER))
            ->setCreatedBy($this->getUser())
            ->setMovementDate(new \DateTimeImmutable('today'));

        $form = $this->createForm(CashMovementFormType::class, $movement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($movement);
            $entityManager->flush();
            $auditLogger->log('create', 'CashMovement', $movement->getIdString(), null, [
                'reference' => $movement->getReference(),
                'amount' => $movement->getAmount(),
                'direction' => $movement->getDirection()->value,
            ], 'success', 'M09', 'Création d’un mouvement de trésorerie');
            $this->addFlash('success', 'Le mouvement a été enregistré.');

            return $this->redirectToRoute('app_treasury_index');
        }

        return $this->render('treasury/movement_form.html.twig', ['form' => $form, 'movement' => $movement]);
    }

    #[Route('/mouvements/{movementId}/rapprocher', name: 'movement_reconcile', methods: ['POST'], requirements: ['movementId' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('treasury.manage')]
    public function reconcile(
        #[MapEntity(id: 'movementId')] CashMovement $movement,
        Request $request,
        TreasuryService $treasuryService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('treasury_reconcile_'.$movement->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $treasuryService->reconcile($movement, $this->getUser());
        $entityManager->flush();
        $auditLogger->log('reconcile', 'CashMovement', $movement->getIdString(), null, ['reference' => $movement->getReference()], 'success', 'M09', 'Rapprochement d’un mouvement de trésorerie');
        $this->addFlash('success', 'Le mouvement a été rapproché.');

        return $this->redirectToRoute('app_treasury_index');
    }

    private function findByUuid(object $repository, string $value): ?object
    {
        if ('' === $value || !Uuid::isValid($value)) {
            return null;
        }

        return $repository->find($value);
    }
}
