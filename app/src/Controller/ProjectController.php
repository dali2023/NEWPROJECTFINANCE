<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Enum\ProjectStatus;
use App\Form\ProjectFormType;
use App\Repository\AuditLogRepository;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use App\Repository\ProjectTypeRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Security\Voter\ProjectVoter;
use App\Service\AuditLogger;
use App\Service\Money;
use App\Service\ReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * M03 — Projets et contrats (§4.3, §7.3, §7.4, §8.1).
 */
#[Route('/projets', name: 'app_project_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProjectController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        ClientRepository $clientRepository,
        ProjectTypeRepository $projectTypeRepository,
        UserRepository $userRepository,
        PermissionChecker $permissionChecker,
    ): Response {
        $user = $this->getUser();
        if (!$this->isGranted('project.view_all') && !$this->isGranted('project.view_own')) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à consulter les projets.');
        }

        // Un utilisateur sans project.view_all ne voit que ses projets.
        $scope = $permissionChecker->canViewAllProjects($user) ? null : $user;

        $filters = [
            'term' => $request->query->getString('q') ?: null,
            'status' => ProjectStatus::tryFrom($request->query->getString('statut')),
            'client' => $this->findByUuid($clientRepository, $request->query->getString('client')),
            'type' => $this->findByUuid($projectTypeRepository, $request->query->getString('type')),
            'manager' => $this->findByUuid($userRepository, $request->query->getString('responsable')),
            'onlyFor' => $scope,
        ];

        return $this->render('project/index.html.twig', [
            'result' => $projectRepository->search($filters, $request->query->getInt('page', 1)),
            'filters' => $filters,
            'statuses' => ProjectStatus::cases(),
            'clients' => $clientRepository->findActiveOrdered(),
            'project_types' => $projectTypeRepository->findActiveOrdered(),
            'managers' => $userRepository->findActiveOrdered(),
            'scope_limited' => null !== $scope,
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('project.manage')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceGenerator $referenceGenerator,
        AuditLogger $auditLogger,
    ): Response {
        $project = new Project();
        $project->setCode($referenceGenerator->next(ReferenceGenerator::MODULE_PROJECT));
        $project->setCreatedBy($this->getUser());
        $project->setManager($this->getUser());

        $form = $this->createForm(ProjectFormType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->guardContractualStatus($form, $project)) {
                return $this->render('project/new.html.twig', ['form' => $form]);
            }

            $this->normalizeMilestones($project);
            $project->recomputeTotals();
            $entityManager->persist($project);
            $entityManager->flush();

            $auditLogger->log('create', 'Project', $project->getIdString(), null, $this->snapshot($project), 'success', 'M03', 'Création d’un projet');

            $this->addFlash('success', sprintf('Le projet %s a été créé.', $project->getCode()));

            return $this->redirectToRoute('app_project_show', ['id' => $project->getIdString()]);
        }

        return $this->render('project/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Project $project, AuditLogRepository $auditLogRepository): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'history' => $auditLogRepository->findForEntity('Project', $project->getIdString(), 20),
            'milestones_total' => $project->getMilestonesTotalHt(),
            'contract_gap' => Money::sub($project->getContractAmountHt(), $project->getMilestonesTotalHt()),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        $before = $this->snapshot($project);
        $previousStatus = $project->getStatus();
        $form = $this->createForm(ProjectFormType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($previousStatus !== $project->getStatus() && !$this->guardContractualStatus($form, $project)) {
                return $this->render('project/edit.html.twig', ['form' => $form, 'project' => $project]);
            }

            $this->normalizeMilestones($project);
            $project->recomputeTotals();
            $entityManager->flush();

            $after = $this->snapshot($project);
            $auditLogger->log(
                $previousStatus !== $project->getStatus() ? 'status_change' : 'update',
                'Project',
                $project->getIdString(),
                $before,
                $after,
                'success',
                'M03',
                $previousStatus !== $project->getStatus()
                    ? sprintf('Statut : %s → %s', $previousStatus->label(), $project->getStatus()->label())
                    : 'Modification d’un projet',
            );

            $this->addFlash('success', 'Le projet a été mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getIdString()]);
        }

        return $this->render('project/edit.html.twig', ['form' => $form, 'project' => $project]);
    }

    /**
     * Clôture d'un projet après validation de sa situation finale (§8.1).
     */
    #[Route('/{id}/cloturer', name: 'close', methods: ['POST'])]
    public function close(Project $project, Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::CLOSE, $project);

        if (!$this->isCsrfTokenValid('project_close_'.$project->getIdString(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $previous = $project->getStatus();
        $project->close($this->getUser());
        $entityManager->flush();

        $auditLogger->log(
            'close',
            'Project',
            $project->getIdString(),
            ['status' => $previous->value],
            ['status' => $project->getStatus()->value, 'closedAt' => $project->getClosedAt()?->format(\DateTimeInterface::ATOM)],
            'success',
            'M03',
            'Clôture du projet',
        );

        $this->addFlash('success', sprintf('Le projet %s est clôturé.', $project->getCode()));

        return $this->redirectToRoute('app_project_show', ['id' => $project->getIdString()]);
    }

    /**
     * Un projet ne devient un contrat signé que si l'utilisateur dispose de la
     * permission de validation (§8.1). Le contrôle est fait côté serveur.
     */
    private function guardContractualStatus(\Symfony\Component\Form\FormInterface $form, Project $project): bool
    {
        if (!$project->getStatus()->isContractual()) {
            return true;
        }

        if ($this->isGranted('project.validate')) {
            return true;
        }

        $form->addError(new FormError('Seul un utilisateur habilité peut valider le statut contractuel d’un projet.'));

        return false;
    }

    private function normalizeMilestones(Project $project): void
    {
        $position = 0;
        foreach ($project->getMilestones() as $milestone) {
            $milestone->setPosition($position++);
            $milestone->setProject($project);
        }
    }

    /**
     * Résout un identifiant UUID issu de la requête, sans risque d'erreur SQL
     * sur une valeur malformée.
     */
    private function findByUuid(object $repository, string $value): ?object
    {
        if ('' === $value || !\Symfony\Component\Uid\Uuid::isValid($value)) {
            return null;
        }

        return $repository->find($value);
    }

    /** @return array<string, mixed> */
    private function snapshot(Project $project): array
    {
        return [
            'code' => $project->getCode(),
            'name' => $project->getName(),
            'status' => $project->getStatus()->value,
            'client' => $project->getClient()?->getName(),
            'contractAmountHt' => $project->getContractAmountHt(),
            'budgetAmount' => $project->getBudgetAmount(),
        ];
    }
}
