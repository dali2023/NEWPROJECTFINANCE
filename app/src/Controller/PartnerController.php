<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BankAccount;
use App\Entity\Partner;
use App\Entity\PartnerTransaction;
use App\Enum\PartnerTransactionStatus;
use App\Enum\PartnerTransactionType;
use App\Form\PartnerFormType;
use App\Form\PartnerTransactionFormType;
use App\Repository\BankAccountRepository;
use App\Repository\PartnerRepository;
use App\Repository\PartnerTransactionRepository;
use App\Service\AuditLogger;
use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use App\Service\PartnerService;
use App\Service\ReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** M10 — Associés : capital, comptes courants et distributions. */
#[Route('/associes', name: 'app_partner_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PartnerController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('partner.view')]
    public function index(PartnerRepository $partnerRepository, PartnerTransactionRepository $transactionRepository): Response
    {
        return $this->render('partner/index.html.twig', [
            'partners' => $partnerRepository->findBy([], ['name' => 'ASC']),
            'result' => $transactionRepository->search(null, null, 1, 10),
            'total_ownership' => $partnerRepository->totalOwnership(),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    #[IsGranted('partner.view')]
    public function export(PartnerRepository $partnerRepository, SpreadsheetExporter $exporter): Response
    {
        $partners = $partnerRepository->findBy([], ['name' => 'ASC']);

        $rows = [];
        foreach ($partners as $partner) {
            $rows[] = [
                $partner->getCode(),
                $partner->getName(),
                $partner->getPartnerType()->label(),
                $partner->getOwnershipPercent(),
                $partner->getCapitalContributed(),
                $partner->getCurrentAccountBalance(),
                $partner->getDividendsReceived(),
                $partner->getEmail(),
                $partner->getPhone(),
                $partner->isActive() ? 'Actif' : 'Inactif',
            ];
        }

        return $exporter->download('associes', 'Actionnariat et comptes associés', [
            Column::text('Code', 12),
            Column::text('Associé', 28),
            Column::text('Forme', 16),
            Column::rate('Part du capital', 14),
            Column::money('Capital apporté'),
            Column::money('Compte courant'),
            Column::money('Dividendes reçus'),
            Column::text('E-mail', 26),
            Column::text('Téléphone', 16),
            Column::text('Statut', 10),
        ], $rows, [
            sprintf('%d associé(s)', \count($partners)),
            'Part du capital renseignée : '.$partnerRepository->totalOwnership().' %',
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('partner.manage')]
    public function new(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $partner = new Partner();
        $partner->setCreatedBy($this->getUser());

        $form = $this->createForm(PartnerFormType::class, $partner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($partner);
            $entityManager->flush();
            $auditLogger->log('create', 'Partner', $partner->getIdString(), null, ['code' => $partner->getCode(), 'name' => $partner->getName()], 'success', 'M10', 'Création d’un associé');
            $this->addFlash('success', 'L’associé a été créé.');

            return $this->redirectToRoute('app_partner_show', ['id' => $partner->getIdString()]);
        }

        return $this->render('partner/form.html.twig', ['form' => $form, 'partner' => $partner, 'is_new' => true]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('partner.view')]
    public function show(Partner $partner, BankAccountRepository $bankAccountRepository): Response
    {
        return $this->render('partner/show.html.twig', [
            'partner' => $partner,
            'accounts' => $bankAccountRepository->findActiveOrdered(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('partner.manage')]
    public function edit(Partner $partner, Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $form = $this->createForm(PartnerFormType::class, $partner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $auditLogger->log('update', 'Partner', $partner->getIdString(), null, ['code' => $partner->getCode()], 'success', 'M10', 'Modification d’un associé');
            $this->addFlash('success', 'L’associé a été mis à jour.');

            return $this->redirectToRoute('app_partner_show', ['id' => $partner->getIdString()]);
        }

        return $this->render('partner/form.html.twig', ['form' => $form, 'partner' => $partner, 'is_new' => false]);
    }

    #[Route('/{id}/operation/nouvelle', name: 'transaction_new', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('partner.manage')]
    public function transactionNew(
        Partner $partner,
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        AuditLogger $auditLogger,
    ): Response {
        $transaction = new PartnerTransaction();
        $transaction->setPartner($partner)
            ->setReference($referenceGenerator->next(ReferenceGenerator::MODULE_PARTNER))
            ->setCreatedBy($this->getUser())
            ->setCurrencyCode((string) $this->getParameter('app.currency'))
            ->setTransactionDate(new \DateTimeImmutable('today'));

        $form = $this->createForm(PartnerTransactionFormType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($transaction);
            $entityManager->flush();
            $auditLogger->log('create', 'PartnerTransaction', $transaction->getIdString(), null, [
                'reference' => $transaction->getReference(),
                'type' => $transaction->getTransactionType()->value,
                'amount' => $transaction->getAmount(),
            ], 'success', 'M10', 'Création d’une opération d’associé');
            $this->addFlash('success', 'L’opération a été enregistrée.');

            return $this->redirectToRoute('app_partner_show', ['id' => $partner->getIdString()]);
        }

        return $this->render('partner/transaction_form.html.twig', [
            'form' => $form,
            'partner' => $partner,
            'transaction' => $transaction,
        ]);
    }

    #[Route('/operations/{transactionId}/valider', name: 'transaction_validate', methods: ['POST'], requirements: ['transactionId' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('partner.validate')]
    public function transactionValidate(
        #[MapEntity(id: 'transactionId')] PartnerTransaction $transaction,
        Request $request,
        PartnerService $partnerService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->checkCsrf('partner_transaction_validate_', $transaction->getIdString(), $request);

        try {
            $partnerService->validate($transaction, $this->getUser());
            $entityManager->flush();
            $auditLogger->log('validate', 'PartnerTransaction', $transaction->getIdString(), null, ['status' => $transaction->getStatus()->value], 'success', 'M10', 'Validation d’une opération d’associé');
            $this->addFlash('success', 'L’opération a été validée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_partner_show', ['id' => $transaction->getPartner()?->getIdString()]);
    }

    #[Route('/operations/{transactionId}/regler', name: 'transaction_pay', methods: ['POST'], requirements: ['transactionId' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('partner.manage')]
    public function transactionPay(
        #[MapEntity(id: 'transactionId')] PartnerTransaction $transaction,
        Request $request,
        PartnerService $partnerService,
        BankAccountRepository $bankAccountRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->checkCsrf('partner_transaction_pay_', $transaction->getIdString(), $request);

        $account = $this->findByUuid($bankAccountRepository, $request->request->getString('account'));

        try {
            $partnerService->pay($transaction, $account instanceof BankAccount ? $account : null, $this->getUser());
            $entityManager->flush();
            $auditLogger->log('pay', 'PartnerTransaction', $transaction->getIdString(), null, ['status' => $transaction->getStatus()->value], 'success', 'M10', 'Règlement d’une opération d’associé');
            $this->addFlash('success', 'L’opération a été réglée.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_partner_show', ['id' => $transaction->getPartner()?->getIdString()]);
    }

    private function checkCsrf(string $prefix, string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($prefix.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    private function findByUuid(object $repository, string $value): ?object
    {
        if ('' === $value || !\Symfony\Component\Uid\Uuid::isValid($value)) {
            return null;
        }

        return $repository->find($value);
    }
}
