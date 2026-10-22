<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FiscalYear;
use App\Entity\User;
use App\Enum\FiscalYearStatus;
use App\Form\FiscalYearFormType;
use App\Repository\FiscalYearRepository;
use App\Service\AuditLogger;
use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * M01 — Exercices financiers (§4.1, §7.12).
 *
 * Un exercice clôturé interdit les écritures ordinaires ; sa réouverture exige
 * une permission spécifique et laisse une trace d'audit (§5.4, REC-014).
 */
#[Route('/admin/exercices', name: 'app_admin_fiscal_year_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class FiscalYearController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(FiscalYearRepository $fiscalYearRepository): Response
    {
        if (!$this->isGranted('admin.referentials') && !$this->isGranted('fiscal_year.close')) {
            throw $this->createAccessDeniedException('Accès aux exercices non autorisé.');
        }

        return $this->render('admin/fiscal_year/index.html.twig', [
            'fiscal_years' => $fiscalYearRepository->findAllOrdered(),
            'current' => $fiscalYearRepository->findCurrent(),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(FiscalYearRepository $fiscalYearRepository, SpreadsheetExporter $exporter): Response
    {
        if (!$this->isGranted('admin.referentials') && !$this->isGranted('fiscal_year.close')) {
            throw $this->createAccessDeniedException('Accès aux exercices non autorisé.');
        }

        $fiscalYears = $fiscalYearRepository->findAllOrdered();

        $rows = [];
        foreach ($fiscalYears as $fiscalYear) {
            $rows[] = [
                $fiscalYear->getLabel(),
                $fiscalYear->getStartDate(),
                $fiscalYear->getEndDate(),
                $fiscalYear->getStatus()->label(),
                $fiscalYear->getClosedAt(),
                $fiscalYear->getClosedBy()?->getFullName(),
            ];
        }

        $current = $fiscalYearRepository->findCurrent();
        $context = [sprintf('%d exercice(s)', \count($fiscalYears))];
        if (null !== $current) {
            $context[] = 'Exercice courant : '.$current->getLabel();
        }

        return $exporter->download('exercices-financiers', 'Exercices financiers', [
            Column::text('Libellé', 14),
            Column::date('Début'),
            Column::date('Fin'),
            Column::text('Statut', 14),
            Column::dateTime('Clôturé le'),
            Column::text('Clôturé par', 24),
        ], $rows, $context);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('admin.referentials')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        FiscalYearRepository $fiscalYearRepository,
        AuditLogger $auditLogger,
    ): Response {
        $fiscalYear = new FiscalYear();
        $fiscalYear->setLabel((string) (new \DateTimeImmutable())->format('Y'));
        $fiscalYear->setStartDate(new \DateTimeImmutable('first day of January'))
            ->setEndDate(new \DateTimeImmutable('last day of December'));
        $fiscalYear->setCreatedBy($this->getUser());

        $form = $this->createForm(FiscalYearFormType::class, $fiscalYear);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($fiscalYearRepository->hasOverlap($fiscalYear->getStartDate(), $fiscalYear->getEndDate(), $fiscalYear)) {
                $this->addFlash('error', 'Cet exercice chevauche une période déjà existante.');
            } else {
                $entityManager->persist($fiscalYear);
                $entityManager->flush();
                $auditLogger->log('create', 'FiscalYear', $fiscalYear->getIdString(), null, [
                    'label' => $fiscalYear->getLabel(),
                    'startDate' => $fiscalYear->getStartDate()?->format('Y-m-d'),
                    'endDate' => $fiscalYear->getEndDate()?->format('Y-m-d'),
                ], 'success', 'M01', 'Création d’un exercice');

                $this->addFlash('success', sprintf('L’exercice %s a été créé.', $fiscalYear->getLabel()));

                return $this->redirectToRoute('app_admin_fiscal_year_index');
            }
        }

        return $this->render('admin/fiscal_year/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('admin.referentials')]
    public function edit(
        FiscalYear $fiscalYear,
        Request $request,
        EntityManagerInterface $entityManager,
        FiscalYearRepository $fiscalYearRepository,
        AuditLogger $auditLogger,
    ): Response {
        $before = [
            'label' => $fiscalYear->getLabel(),
            'startDate' => $fiscalYear->getStartDate()?->format('Y-m-d'),
            'endDate' => $fiscalYear->getEndDate()?->format('Y-m-d'),
            'status' => $fiscalYear->getStatus()->value,
        ];

        $form = $this->createForm(FiscalYearFormType::class, $fiscalYear);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($fiscalYearRepository->hasOverlap($fiscalYear->getStartDate(), $fiscalYear->getEndDate(), $fiscalYear)) {
                $this->addFlash('error', 'Cet exercice chevauche une période déjà existante.');
            } else {
                $entityManager->flush();
                $auditLogger->log('update', 'FiscalYear', $fiscalYear->getIdString(), $before, [
                    'label' => $fiscalYear->getLabel(),
                    'startDate' => $fiscalYear->getStartDate()?->format('Y-m-d'),
                    'endDate' => $fiscalYear->getEndDate()?->format('Y-m-d'),
                    'status' => $fiscalYear->getStatus()->value,
                ], 'success', 'M01', 'Modification d’un exercice');

                $this->addFlash('success', 'L’exercice a été mis à jour.');

                return $this->redirectToRoute('app_admin_fiscal_year_index');
            }
        }

        return $this->render('admin/fiscal_year/edit.html.twig', ['form' => $form, 'fiscal_year' => $fiscalYear]);
    }

    #[Route('/{id}/cloturer', name: 'close', methods: ['POST'])]
    public function close(FiscalYear $fiscalYear, Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isGranted('fiscal_year.close')) {
            throw $this->createAccessDeniedException('Clôture non autorisée.');
        }

        if (!$this->isCsrfTokenValid('fiscal_year_close_'.$fiscalYear->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $fiscalYear->close($user);
        $entityManager->flush();

        $auditLogger->log('close', 'FiscalYear', $fiscalYear->getIdString(), ['status' => FiscalYearStatus::Open->value], [
            'status' => $fiscalYear->getStatus()->value,
            'closedAt' => $fiscalYear->getClosedAt()?->format(\DateTimeInterface::ATOM),
        ], 'success', 'M01', 'Clôture d’un exercice');

        $this->addFlash('success', sprintf('L’exercice %s est clôturé.', $fiscalYear->getLabel()));

        return $this->redirectToRoute('app_admin_fiscal_year_index');
    }

    /**
     * Réouverture exceptionnelle : permission dédiée + motif obligatoire +
     * trace d'audit (§4.1).
     */
    #[Route('/{id}/reouvrir', name: 'reopen', methods: ['POST'])]
    public function reopen(FiscalYear $fiscalYear, Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isGranted('fiscal_year.reopen')) {
            throw $this->createAccessDeniedException('Réouverture non autorisée.');
        }

        if (!$this->isCsrfTokenValid('fiscal_year_reopen_'.$fiscalYear->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $reason = trim($request->request->getString('motif'));
        if (mb_strlen($reason) < 10) {
            $this->addFlash('error', 'Un motif de réouverture d’au moins 10 caractères est obligatoire.');

            return $this->redirectToRoute('app_admin_fiscal_year_index');
        }

        $fiscalYear->setStatus(FiscalYearStatus::Open);
        $entityManager->flush();

        $auditLogger->log('reopen', 'FiscalYear', $fiscalYear->getIdString(), ['status' => FiscalYearStatus::Closed->value], [
            'status' => $fiscalYear->getStatus()->value,
        ], 'success', 'M01', 'Réouverture d’un exercice — motif : '.$reason);

        $this->addFlash('success', sprintf('L’exercice %s a été réouvert.', $fiscalYear->getLabel()));

        return $this->redirectToRoute('app_admin_fiscal_year_index');
    }
}
