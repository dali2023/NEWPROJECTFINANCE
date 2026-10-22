<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

/**
 * Contrôle des permissions unitaires (§3.6).
 *
 * Les permissions sont portées par les rôles en base, ce qui permet de les
 * ajuster sans redéploiement (docs/03-matrice-permissions.md). L'administrateur
 * dispose du joker « * » pour la gestion technique, mais les règles de
 * séparation des tâches restent appliquées par les contrôleurs métier.
 */
final class PermissionChecker
{
    public function has(?User $user, string $permission): bool
    {
        if (null === $user || !$user->isActive()) {
            return false;
        }

        if ($user->hasRoleCode('ROLE_ADMIN')) {
            return true;
        }

        return \in_array($permission, $user->getPermissionCodes(), true);
    }

    /** L'utilisateur peut-il consulter tous les projets ou seulement les siens ? */
    public function canViewAllProjects(?User $user): bool
    {
        return $this->has($user, 'project.view_all');
    }

    /** L'utilisateur peut-il consulter tous les contrats ou seulement les siens ? */
    public function canViewAllContracts(?User $user): bool
    {
        return $this->has($user, 'contract.view_all');
    }

    /** Portée de lecture des contrats : null signifie « tous les contrats ». */
    public function contractScope(?User $user): ?User
    {
        if (null === $user) {
            return null;
        }

        return $this->canViewAllContracts($user) ? null : $user;
    }

    /**
     * Portée de lecture des projets : null signifie « tous les projets ».
     * Utilisée par les contrôleurs pour restreindre les requêtes (§3.5).
     */
    public function projectScope(?User $user): ?User
    {
        if (null === $user) {
            return null;
        }

        return $this->canViewAllProjects($user) ? null : $user;
    }
}
