<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Supplier;
use App\Form\SupplierFormType;
use App\Repository\SupplierRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * M02 — Fournisseurs (§4.2).
 */
#[Route('/fournisseurs', name: 'app_supplier_')]
#[IsGranted('supplier.view')]
final class SupplierController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SupplierRepository $supplierRepository): Response
    {
        $term = $request->query->getString('q') ?: null;
        $activeOnly = $request->query->has('actifs') ? true : null;

        return $this->render('supplier/index.html.twig', [
            'result' => $supplierRepository->search($term, $activeOnly, $request->query->getInt('page', 1)),
            'term' => $term,
            'active_only' => $activeOnly,
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('supplier.manage')]
    public function new(Request $request, EntityManagerInterface $entityManager, SupplierRepository $supplierRepository, AuditLogger $auditLogger): Response
    {
        $supplier = new Supplier();
        $form = $this->createForm(SupplierFormType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($supplierRepository->codeExists($supplier->getCode())) {
                $this->addFlash('error', 'Ce code fournisseur est déjà utilisé.');

                return $this->render('supplier/new.html.twig', ['form' => $form]);
            }

            $supplier->setCreatedBy($this->getUser());
            $entityManager->persist($supplier);
            $entityManager->flush();

            $auditLogger->log('create', 'Supplier', $supplier->getIdString(), null, $this->snapshot($supplier), 'success', 'M02', 'Création d’un fournisseur');

            $this->addFlash('success', sprintf('Le fournisseur %s a été enregistré.', $supplier->getName()));

            return $this->redirectToRoute('app_supplier_edit', ['id' => $supplier->getIdString()]);
        }

        return $this->render('supplier/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('supplier.manage')]
    public function edit(
        Supplier $supplier,
        Request $request,
        EntityManagerInterface $entityManager,
        SupplierRepository $supplierRepository,
        AuditLogger $auditLogger,
    ): Response {
        $before = $this->snapshot($supplier);
        $form = $this->createForm(SupplierFormType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($supplierRepository->codeExists($supplier->getCode(), $supplier)) {
                $this->addFlash('error', 'Ce code fournisseur est déjà utilisé.');

                return $this->render('supplier/edit.html.twig', ['form' => $form, 'supplier' => $supplier]);
            }

            $entityManager->flush();
            $auditLogger->log('update', 'Supplier', $supplier->getIdString(), $before, $this->snapshot($supplier), 'success', 'M02', 'Modification d’un fournisseur');

            $this->addFlash('success', 'Les informations du fournisseur ont été mises à jour.');

            return $this->redirectToRoute('app_supplier_index');
        }

        return $this->render('supplier/edit.html.twig', ['form' => $form, 'supplier' => $supplier]);
    }

    #[Route('/{id}/statut', name: 'toggle', methods: ['POST'])]
    #[IsGranted('supplier.manage')]
    public function toggle(Supplier $supplier, Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('supplier_toggle_'.$supplier->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $wasActive = $supplier->isActive();
        $supplier->setIsActive(!$wasActive);
        $entityManager->flush();

        $auditLogger->log(
            'update',
            'Supplier',
            $supplier->getIdString(),
            ['isActive' => $wasActive],
            ['isActive' => $supplier->isActive()],
            'success',
            'M02',
            $supplier->isActive() ? 'Réactivation du fournisseur' : 'Désactivation du fournisseur',
        );

        $this->addFlash('success', $supplier->isActive() ? 'Le fournisseur a été réactivé.' : 'Le fournisseur a été désactivé.');

        return $this->redirectToRoute('app_supplier_index');
    }

    /** @return array<string, mixed> */
    private function snapshot(Supplier $supplier): array
    {
        return [
            'code' => $supplier->getCode(),
            'name' => $supplier->getName(),
            'serviceCategory' => $supplier->getServiceCategory(),
            'email' => $supplier->getEmail(),
            'isActive' => $supplier->isActive(),
        ];
    }
}
