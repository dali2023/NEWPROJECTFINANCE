<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserFormType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * M14 — Comptes utilisateurs (§3.1, §7.12).
 */
#[Route('/admin/utilisateurs', name: 'app_admin_user_')]
#[IsGranted('admin.users')]
final class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $term = $request->query->getString('q') ?: null;

        return $this->render('admin/user/index.html.twig', [
            'result' => $userRepository->search($term, null, $request->query->getInt('page', 1)),
            'term' => $term,
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        AuditLogger $auditLogger,
    ): Response {
        $user = new User();
        $form = $this->createForm(UserFormType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($userRepository->identifierExists($user->getUsername()) || $userRepository->identifierExists($user->getEmail())) {
                $this->addFlash('error', 'Ce nom de connexion ou cette adresse électronique est déjà utilisé.');
            } else {
                $plainPassword = (string) $form->get('plainPassword')->getData();
                $user->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword));
                $entityManager->persist($user);
                $entityManager->flush();

                $auditLogger->log('create', 'User', $user->getIdString(), null, [
                    'username' => $user->getUsername(),
                    'roles' => $user->getRoles(),
                ], 'success', 'M14', 'Création d’un compte utilisateur');

                $this->addFlash('success', sprintf('Le compte %s a été créé.', $user->getUsername()));

                return $this->redirectToRoute('app_admin_user_index');
            }
        }

        return $this->render('admin/user/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        AuditLogger $auditLogger,
    ): Response {
        $before = [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
        ];

        $form = $this->createForm(UserFormType::class, $user, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $duplicate = $userRepository->findOneByIdentifier($user->getUsername());
            $duplicateEmail = $userRepository->findOneByIdentifier($user->getEmail());

            if (null !== $duplicate && $duplicate->getIdString() !== $user->getIdString()) {
                $this->addFlash('error', 'Ce nom de connexion est déjà utilisé.');

                return $this->render('admin/user/edit.html.twig', ['form' => $form, 'user' => $user]);
            }

            if (null !== $duplicateEmail && $duplicateEmail->getIdString() !== $user->getIdString()) {
                $this->addFlash('error', 'Cette adresse électronique est déjà utilisée.');

                return $this->render('admin/user/edit.html.twig', ['form' => $form, 'user' => $user]);
            }

            // Dernier administrateur actif : désactivation interdite (§3.1).
            $wasAdmin = $user->hasRoleCode('ROLE_ADMIN');
            $willBeAdmin = $this->hasAdminRole($user);

            if ($wasAdmin && (!$willBeAdmin || !$user->isActive()) && $userRepository->countActiveAdministrators() <= 1) {
                $this->addFlash('error', 'Le dernier administrateur actif ne peut pas être désactivé ni perdre son rôle.');

                return $this->render('admin/user/edit.html.twig', ['form' => $form, 'user' => $user]);
            }

            $plainPassword = (string) $form->get('plainPassword')->getData();
            if ('' !== trim($plainPassword)) {
                $user->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword));
            }

            $entityManager->flush();

            $auditLogger->log('update', 'User', $user->getIdString(), $before, [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
            ], 'success', 'M14', 'Modification d’un compte utilisateur');

            $this->addFlash('success', 'Le compte a été mis à jour.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', ['form' => $form, 'user' => $user]);
    }

    private function hasAdminRole(User $user): bool
    {
        foreach ($user->getAssignedRoles() as $role) {
            if ('ROLE_ADMIN' === $role->getCode()) {
                return true;
            }
        }

        return false;
    }
}
