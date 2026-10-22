<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BankAccount;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\PaymentMethod;
use App\Form\InvoiceFormType;
use App\Repository\BankAccountRepository;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Security\PermissionChecker;
use App\Service\AuditLogger;
use App\Service\ExpenseService;
use App\Service\InvoiceService;
use App\Service\ReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * M04 — Facturation client.
 *
 * Le statut d'une facture est indépendant de celui de l'échéance contractuelle :
 * une facture peut être prête sans être validée, et une échéance prête n'est
 * jamais réputée facturée avant la validation de la facture correspondante.
 */
#[Route('/factures', name: 'app_invoice_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class InvoiceController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('invoice.view')]
    public function index(
        Request $request,
        InvoiceRepository $invoiceRepository,
        ClientRepository $clientRepository,
    ): Response {
        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'client' => $this->findByUuid($clientRepository, $request->query->getString('client')),
            'status' => InvoiceStatus::tryFrom($request->query->getString('statut')),
            'type' => InvoiceType::tryFrom($request->query->getString('type')),
            'overdueOnly' => $request->query->getBoolean('retard') ?: null,
        ];

        return $this->render('invoice/index.html.twig', [
            'result' => $invoiceRepository->search($filters, $request->query->getInt('page', 1)),
            'filters' => $filters,
            'statuses' => InvoiceStatus::cases(),
            'types' => InvoiceType::cases(),
            'clients' => $clientRepository->findActiveOrdered(),
            'open_balance' => $invoiceRepository->sumOpenBalance(),
            'overdue_balance' => $invoiceRepository->sumOverdueBalance(),
        ]);
    }

    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('invoice.manage')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        AuditLogger $auditLogger,
    ): Response {
        $invoice = new Invoice();
        $invoice->setNumber($referenceGenerator->next(ReferenceGenerator::MODULE_INVOICE))
            ->setIssueDate(new \DateTimeImmutable('today'))
            ->setDueDate(new \DateTimeImmutable('+30 days'))
            ->setCurrencyCode((string) $this->getParameter('app.currency'))
            ->setCreatedBy($this->getUser());

        $form = $this->createForm(InvoiceFormType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($invoice->getLines()->isEmpty()) {
                $form->addError(new FormError('Ajoutez au moins une ligne à la facture.'));
            } else {
                foreach ($invoice->getLines() as $line) {
                    $line->recompute();
                }
                $invoice->recomputeTotals();
                $entityManager->persist($invoice);
                $entityManager->flush();

                $auditLogger->log('create', 'Invoice', $invoice->getIdString(), null, [
                    'number' => $invoice->getNumber(),
                    'client' => $invoice->getClient()?->getName(),
                    'amountTtc' => $invoice->getAmountTtc(),
                ], 'success', 'M04', 'Création d’une facture');

                $this->addFlash('success', sprintf('La facture %s a été créée en brouillon.', $invoice->getNumber()));

                return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
            }
        }

        return $this->render('invoice/new.html.twig', ['form' => $form, 'invoice' => $invoice]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('invoice.view')]
    public function show(
        Invoice $invoice,
        BankAccountRepository $bankAccountRepository,
    ): Response {
        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
            'accounts' => $bankAccountRepository->findActiveOrdered(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('invoice.manage')]
    public function edit(
        Invoice $invoice,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$invoice->getStatus()->isEditable()) {
            throw $this->createAccessDeniedException('Seule une facture brouillon peut être modifiée.');
        }

        $before = ['number' => $invoice->getNumber(), 'amountHt' => $invoice->getAmountHt(), 'amountTtc' => $invoice->getAmountTtc()];
        $form = $this->createForm(InvoiceFormType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($invoice->getLines()->isEmpty()) {
                $form->addError(new FormError('Ajoutez au moins une ligne à la facture.'));
            } else {
                foreach ($invoice->getLines() as $line) {
                    $line->recompute();
                }
                $invoice->recomputeTotals();
                $entityManager->flush();

                $auditLogger->log('update', 'Invoice', $invoice->getIdString(), $before, [
                    'number' => $invoice->getNumber(),
                    'amountHt' => $invoice->getAmountHt(),
                    'amountTtc' => $invoice->getAmountTtc(),
                ], 'success', 'M04', 'Modification d’une facture brouillon');

                $this->addFlash('success', 'La facture a été mise à jour.');

                return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
            }
        }

        return $this->render('invoice/edit.html.twig', ['form' => $form, 'invoice' => $invoice]);
    }

    #[Route('/{id}/valider', name: 'validate', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('invoice.manage')]
    public function validateInvoice(
        Invoice $invoice,
        Request $request,
        InvoiceService $invoiceService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('invoice_validate_'.$invoice->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $invoiceService->validate($invoice, $this->getUser());
            $entityManager->flush();
            $auditLogger->log('validate', 'Invoice', $invoice->getIdString(), null, [
                'number' => $invoice->getNumber(),
                'amountTtc' => $invoice->getAmountTtc(),
            ], 'success', 'M04', 'Validation d’une facture');
            $this->addFlash('success', 'La facture a été validée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
    }

    #[Route('/{id}/envoyer', name: 'send', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('invoice.manage')]
    public function send(
        Invoice $invoice,
        Request $request,
        InvoiceService $invoiceService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('invoice_send_'.$invoice->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $invoiceService->markSent($invoice);
        $entityManager->flush();
        $auditLogger->log('send', 'Invoice', $invoice->getIdString(), null, ['number' => $invoice->getNumber()], 'success', 'M04', 'Marquage d’une facture comme envoyée');
        $this->addFlash('success', 'La facture est marquée comme envoyée.');

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
    }

    #[Route('/{id}/annuler', name: 'cancel', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('invoice.cancel')]
    public function cancel(
        Invoice $invoice,
        Request $request,
        InvoiceService $invoiceService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('invoice_cancel_'.$invoice->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $reason = trim($request->request->getString('reason'));
        if ('' === $reason) {
            $this->addFlash('error', 'Un motif d’annulation est obligatoire.');

            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
        }

        try {
            $invoiceService->cancel($invoice, $this->getUser(), $reason);
            $entityManager->flush();
            $auditLogger->log('cancel', 'Invoice', $invoice->getIdString(), null, ['reason' => $reason], 'success', 'M04', 'Annulation d’une facture');
            $this->addFlash('success', 'La facture a été annulée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
    }

    #[Route('/{id}/reglement', name: 'payment', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('receipt.manage')]
    public function payment(
        Invoice $invoice,
        Request $request,
        InvoiceService $invoiceService,
        BankAccountRepository $bankAccountRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('invoice_payment_'.$invoice->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $method = PaymentMethod::tryFrom($request->request->getString('method')) ?? PaymentMethod::BankTransfer;
        $date = $this->parseDate($request->request->getString('date')) ?? new \DateTimeImmutable('today');
        $account = $this->findByUuid($bankAccountRepository, $request->request->getString('account'));

        try {
            $payment = $invoiceService->registerPayment(
                $invoice,
                $request->request->getString('amount'),
                $date,
                $method,
                $account instanceof BankAccount ? $account : null,
                $request->request->getString('bank_reference') ?: null,
                $request->request->getString('notes') ?: null,
                $this->getUser(),
            );
            $entityManager->flush();
            $auditLogger->log('payment', 'Invoice', $invoice->getIdString(), null, [
                'payment' => $payment->getReference(),
                'amount' => $payment->getAmount(),
                'method' => $method->value,
            ], 'success', 'M04', 'Encaissement d’une facture');
            $this->addFlash('success', sprintf('Le règlement %s a été enregistré.', $payment->getReference()));
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getIdString()]);
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
