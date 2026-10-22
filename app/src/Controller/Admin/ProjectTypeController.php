<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProjectType;
use App\Form\ProjectTypeFormType;
use App\Repository\ProjectTypeRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * M01 — Typologie des projets (§4.3, §7.12).
 */
#[Route('/admin/types-projet', name: 'app_admin_project_type_')]
#[IsGranted('admin.referentials')]
final class ProjectTypeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProjectTypeRepository $projectTypeRepository): Response
    {
        return $this->render('admin/project_type/index.html.twig', [
            'project_types' => $projectTypeRepository->findAllOrdered(),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ProjectTypeRepository $projectTypeRepository, AuditLogger $auditLogger): Response
    {
        $projectType = new ProjectType();
        $form = $this->createForm(ProjectTypeFormType::class, $projectType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($projectTypeRepository->codeExists($projectType->getCode())) {
                $this->addFlash('error', 'Ce code de type de projet existe déjà.');
            } else {
                $entityManager->persist($projectType);
                $entityManager->flush();
                $auditLogger->log('create', 'ProjectType', $projectType->getIdString(), null, [
                    'code' => $projectType->getCode(),
                    'label' => $projectType->getLabel(),
                ], 'success', 'M01', 'Création d’un type de projet');

                $this->addFlash('success', 'Le type de projet a été créé.');

                return $this->redirectToRoute('app_admin_project_type_index');
            }
        }

        return $this->render('admin/project_type/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        ProjectType $projectType,
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectTypeRepository $projectTypeRepository,
        AuditLogger $auditLogger,
    ): Response {
        $before = ['code' => $projectType->getCode(), 'label' => $projectType->getLabel(), 'isActive' => $projectType->isActive()];
        $form = $this->createForm(ProjectTypeFormType::class, $projectType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($projectTypeRepository->codeExists($projectType->getCode(), $projectType)) {
                $this->addFlash('error', 'Ce code de type de projet existe déjà.');
            } else {
                $entityManager->flush();
                $auditLogger->log('update', 'ProjectType', $projectType->getIdString(), $before, [
                    'code' => $projectType->getCode(),
                    'label' => $projectType->getLabel(),
                    'isActive' => $projectType->isActive(),
                ], 'success', 'M01', 'Modification d’un type de projet');

                $this->addFlash('success', 'Le type de projet a été mis à jour.');

                return $this->redirectToRoute('app_admin_project_type_index');
            }
        }

        return $this->render('admin/project_type/edit.html.twig', ['form' => $form, 'project_type' => $projectType]);
    }
}
