<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\PermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter générique des permissions applicatives.
 *
 * Tout attribut de la forme `domaine.action` (par exemple `project.manage`,
 * `partner.view`) est résolu contre les permissions du rôle de l'utilisateur.
 * Le contrôle est effectué côté serveur : masquer un bouton ne suffit jamais
 * (§3.6, §9.2).
 *
 * La journalisation des refus est assurée par App\EventListener\AccessDeniedListener,
 * afin de ne tracer que les véritables tentatives d'accès et non les simples
 * vérifications d'affichage des gabarits.
 */
final class PermissionVoter extends Voter
{
    public function __construct(private readonly PermissionChecker $permissionChecker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return 1 === preg_match('/^[a-z_]+\.[a-z_]+$/', $attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->permissionChecker->has($user, $attribute);
    }
}
