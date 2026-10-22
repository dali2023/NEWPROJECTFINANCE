<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Form\RolePermissionsFormType;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
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
 * M14 — Rôles et permissions (§3, §7.12).
 */
#[Route('/admin/roles', name: 'app_admin_role_')]
#[IsGranted('admin.users')]
final class RoleController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(RoleRepository $roleRepository, PermissionRepository $permissionRepository): Response
    {
        $roles = $roleRepository->findAllOrdered();
        $userCounts = [];
        foreach ($roles as $role) {
            $userCounts[$role->getIdString()] = \count($role->getUsers());
        }

        return $this->render('admin/role/index.html.twig', [
            'roles' => $roles,
            'user_counts' => $userCounts,
            'permissions_count' => \count($permissionRepository->findAll()),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(RoleRepository $roleRepository, SpreadsheetExporter $exporter): Response
    {
        $roles = $roleRepository->findAllOrdered();

        $rows = [];
        foreach ($roles as $role) {
            $rows[] = [
                $role->getLabel(),
                $role->getCode(),
                $role->getDescription(),
                \count($role->getPermissions()),
                \count($role->getUsers()),
                $role->isSystem() ? 'Système' : 'Personnalisé',
            ];
        }

        return $exporter->download('roles', 'Rôles et permissions', [
            Column::text('Rôle', 26),
            Column::text('Code technique', 22),
            Column::text('Description', 44),
            Column::integer('Permissions'),
            Column::integer('Utilisateurs'),
            Column::text('Type', 14),
        ], $rows, [sprintf('%d rôle(s)', \count($roles))]);
    }

    #[Route('/{id}', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Role $role,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $before = array_map(
            static fn ($permission): string => $permission->getCode(),
            $role->getPermissions()->toArray(),
        );
        sort($before);

        $form = $this->createForm(RolePermissionsFormType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $after = array_map(
                static fn ($permission): string => $permission->getCode(),
                $role->getPermissions()->toArray(),
            );
            sort($after);

            $auditLogger->log('permission_change', 'Role', $role->getIdString(), ['permissions' => $before], [
                'permissions' => $after,
            ], 'success', 'M14', sprintf('Modification des permissions du rôle %s', $role->getLabel()));

            $this->addFlash('success', 'Les permissions du rôle ont été mises à jour.');

            return $this->redirectToRoute('app_admin_role_index');
        }

        return $this->render('admin/role/edit.html.twig', [
            'form' => $form,
            'role' => $role,
        ]);
    }
}
