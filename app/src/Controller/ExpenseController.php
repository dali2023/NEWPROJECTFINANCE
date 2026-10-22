<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BankAccount;
use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Enum\PaymentMethod;
use App\Form\ExpenseFormType;
use App\Repository\BankAccountRepository;
use App\Repository\ExpenseRepository;
use App\Repository\ProjectRepository;
use App\Repository\SupplierRepository;
use App\Service\AuditLogger;
use App\Service\ExpenseService;
use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use App\Service\ReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * M05 — Charges et dépenses.
 *
 * La séparation des tâches est appliquée dans le domaine : l'auteur d'une
 * dépense ne peut jamais l'approuver ni la rejeter lui-même.
 */
#[Route('/charges', name: 'app_expense_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ExpenseController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('expense.view')]
    public function index(
        Request $request,
        ExpenseRepository $expenseRepository,
        SupplierRepository $supplierRepository,
        ProjectRepository $projectRepository,
    ): Response {
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'supplier' => $this->findByUuid($supplierRepository, $request->query->getString('fournisseur')),
            'project' => $this->findByUuid($projectRepository, $request->query->getString('projet')),
            'status' => ExpenseStatus::tryFrom($request->query->getString('statut')),
        ];

        return $this->render('expense/index.html.twig', [
            'result' => $expenseRepository->search($filters, $request->query->getInt('page', 1)),
            'filters' => $filters,
            'statuses' => ExpenseStatus::cases(),
            'suppliers' => $supplierRepository->findActiveOrdered(),
            'projects' => $projectRepository->search([], 1, 200)['items'],
            'approved_total' => $expenseRepository->sumApproved(),
            'pending_total' => $expenseRepository->sumPending(),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    #[IsGranted('expense.view')]
    public function export(
        Request $request,
        ExpenseRepository $expenseRepository,
        SupplierRepository $supplierRepository,
        ProjectRepository $projectRepository,
        SpreadsheetExporter $exporter,
    ): Response {
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'supplier' => $this->findByUuid($supplierRepository, $request->query->getString('fournisseur')),
            'project' => $this->findByUuid($projectRepository, $request->query->getString('projet')),
            'status' => ExpenseStatus::tryFrom($request->query->getString('statut')),
        ];

        $result = $expenseRepository->search($filters, 1, 10000);

        $rows = [];
        foreach ($result['items'] as $expense) {
            $rows[] = [
                $expense->getReference(),
                $expense->getLabel(),
                $expense->getSupplier()?->getName(),
                $expense->getProject()?->getCode(),
                $expense->getCategory()->label(),
                $expense->getExpenseDate(),
                $expense->getDueDate(),
                $expense->getStatus()->label(),
                $expense->getAmountHt(),
                $expense->getVatAmount(),
                $expense->getAmountTtc(),
                $expense->getBalance(),
            ];
        }

        $context = [];
        if (null !== $filters['term']) {
            $context[] = 'Recherche : '.$filters['term'];
        }
        $context[] = $result['total'].' charge(s)';
        $context[] = 'Total approuvé : '.$expenseRepository->sumApproved().' TND';

        return $exporter->download('charges', 'Charges et dépenses', [
            Column::text('Référence', 18),
            Column::text('Libellé', 32),
            Column::text('Fournisseur', 24),
            Column::text('Projet', 14),
            Column::text('Catégorie', 20),
            Column::date('Date'),
            Column::date('Échéance'),
            Column::text('Statut', 16),
            Column::money('HT'),
            Column::money('TVA'),
            Column::money('TTC'),
            Column::money('Reste'),
        ], $rows, $context);
    }

    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('expense.propose')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        AuditLogger $auditLogger,
    ): Response {
        $expense = new Expense();
        $expense->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_EXPENSE))
            ->setCreatedBy($this->getUser())
            ->setExpenseDate(new \DateTimeImmutable('today'))
            ->setDueDate(new \DateTimeImmutable('+30 days'))
            ->setCurrencyCode((string) $this->getParameter('app.currency'));

        $form = $this->createForm(ExpenseFormType::class, $expense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $expense->recomputeTotals();
            $entityManager->persist($expense);
            $entityManager->flush();

            $auditLogger->log('create', 'Expense', $expense->getIdString(), null, [
                'reference' => $expense->getReference(),
                'amountTtc' => $expense->getAmountTtc(),
                'category' => $expense->getCategory()->value,
            ], 'success', 'M05', 'Création d’une charge');

            $this->addFlash('success', sprintf('La charge %s a été créée en brouillon.', $expense->getReference()));

            return $this->redirectToRoute('app_expense_show', ['id' => $expense->getIdString()]);
        }

        return $this->render('expense/new.html.twig', ['form' => $form, 'expense' => $expense]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('expense.view')]
    public function show(Expense $expense, BankAccountRepository $bankAccountRepository): Response
    {
        return $this->render('expense/show.html.twig', [
            'expense' => $expense,
            'accounts' => $bankAccountRepository->findActiveOrdered(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('expense.manage')]
    public function edit(
        Expense $expense,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$expense->getStatus()->isEditable()) {
            throw $this->createAccessDeniedException('Cette charge ne peut plus être modifiée.');
        }

        $before = ['amountHt' => $expense->getAmountHt(), 'label' => $expense->getLabel()];
        $form = $this->createForm(ExpenseFormType::class, $expense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $expense->recomputeTotals();
            $entityManager->flush();
            $auditLogger->log('update', 'Expense', $expense->getIdString(), $before, [
                'amountHt' => $expense->getAmountHt(),
                'label' => $expense->getLabel(),
            ], 'success', 'M05', 'Modification d’une charge');
            $this->addFlash('success', 'La charge a été mise à jour.');

            return $this->redirectToRoute('app_expense_show', ['id' => $expense->getIdString()]);
        }

        return $this->render('expense/edit.html.twig', ['form' => $form, 'expense' => $expense]);
    }

    #[Route('/{id}/soumettre', name: 'submit', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('expense.manage')]
    public function submit(Expense $expense, Request $request, ExpenseService $expenseService, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->checkCsrf('expense_submit_', $expense->getIdString(), $request);

        try {
            $expenseService->submit($expense);
            $entityManager->flush();
            $auditLogger->log('submit', 'Expense', $expense->getIdString(), null, null, 'success', 'M05', 'Soumission d’une charge');
            $this->addFlash('success', 'La charge a été soumise pour approbation.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_expense_show', ['id' => $expense->getIdString()]);
    }

    #[Route('/{id}/approuver', name: 'approve', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('expense.approve')]
    public function approve(Expense $expense, Request $request, ExpenseService $expenseService, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->checkCsrf('expense_approve_', $expense->getIdString(), $request);

        try {
            $expenseService->approve($expense, $this->getUser());
            $entityManager->flush();
            $auditLogger->log('approve', 'Expense', $expense->getIdString(), null, ['status' => $expense->getStatus()->value], 'success', 'M05', 'Approbation d’une charge');
            $this->addFlash('success', 'La charge a été approuvée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_expense_show', ['id' => $expense->getIdString()]);
    }

    #[Route('/{id}/rejeter', name: 'reject', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('expense.approve')]
    public function reject(Expense $expense, Request $request, ExpenseService $expenseService, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->checkCsrf('expense_reject_', $expense->getIdString(), $request);

        try {
            $expenseService->reject($expense, $this->getUser(), $request->request->getString('reason'));
            $entityManager->flush();
            $auditLogger->log('reject', 'Expense', $expense->getIdString(), null, ['reason' => $expense->getRejectionReason()], 'success', 'M05', 'Rejet d’une charge');
            $this->addFlash('success', 'La charge a été rejetée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_expense_show', ['id' => $expense->getIdString()]);
    }

    #[Route('/{id}/paiement', name: 'payment', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('expense.manage')]
    public function payment(
        Expense $expense,
        Request $request,
        ExpenseService $expenseService,
        BankAccountRepository $bankAccountRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->checkCsrf('expense_payment_', $expense->getIdString(), $request);

        $method = PaymentMethod::tryFrom($request->request->getString('method')) ?? PaymentMethod::BankTransfer;
        $date = $this->parseDate($request->request->getString('date')) ?? new \DateTimeImmutable('today');
        $account = $this->findByUuid($bankAccountRepository, $request->request->getString('account'));

        try {
            $payment = $expenseService->registerPayment(
                $expense,
                $request->request->getString('amount'),
                $date,
                $method,
                $account instanceof BankAccount ? $account : null,
                $request->request->getString('bank_reference') ?: null,
                $request->request->getString('notes') ?: null,
                $this->getUser(),
            );
            $entityManager->flush();
            $auditLogger->log('payment', 'Expense', $expense->getIdString(), null, [
                'payment' => $payment->getReference(),
                'amount' => $payment->getAmount(),
            ], 'success', 'M05', 'Paiement d’une charge');
            $this->addFlash('success', sprintf('Le paiement %s a été enregistré.', $payment->getReference()));
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_expense_show', ['id' => $expense->getIdString()]);
    }

    private function checkCsrf(string $prefix, string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($prefix.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
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
}
