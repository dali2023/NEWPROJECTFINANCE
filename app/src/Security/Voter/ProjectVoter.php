<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Security\PermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Habilitations portant sur un projet précis (§3.6, REC-015).
 *
 * - VIEW : `project.view_all`, ou `project.view_own` pour les projets dont
 *   l'utilisateur est responsable ou membre.
 * - EDIT : `project.manage` et projet non clôturé.
 * - CLOSE : `project.close` et projet non déjà clôturé.
 * - VALIDATE : `project.validate` pour changer le statut contractuel.
 */
final class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const CLOSE = 'PROJECT_CLOSE';
    public const VALIDATE = 'PROJECT_VALIDATE';
    public const MANAGE_MILESTONES = 'PROJECT_MANAGE_MILESTONES';

    public function __construct(private readonly PermissionChecker $permissionChecker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Project
            && \in_array($attribute, [self::VIEW, self::EDIT, self::CLOSE, self::VALIDATE, self::MANAGE_MILESTONES], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        /** @var Project $project */
        $project = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($user, $project),
            self::EDIT => $this->canEdit($user, $project),
            self::VALIDATE => $this->canEdit($user, $project)
                && $this->permissionChecker->has($user, 'project.validate'),
            self::CLOSE => $this->permissionChecker->has($user, 'project.close')
                && ProjectStatus::Closed !== $project->getStatus(),
            self::MANAGE_MILESTONES => $this->canEdit($user, $project)
                && $this->permissionChecker->has($user, 'project.manage'),
            default => false,
        };
    }

    private function canView(User $user, Project $project): bool
    {
        if ($project->isDeleted()) {
            return false;
        }

        if ($this->permissionChecker->has($user, 'project.view_all')) {
            return true;
        }

        return $this->permissionChecker->has($user, 'project.view_own')
            && $this->isMemberOf($user, $project);
    }

    private function canEdit(User $user, Project $project): bool
    {
        if (!$this->permissionChecker->has($user, 'project.manage')) {
            return false;
        }

        if ($project->isDeleted()) {
            return false;
        }

        // Un projet clôturé est figé : seule une réouverture tracée permet de
        // le modifier (§5.4).
        if (ProjectStatus::Closed === $project->getStatus()) {
            return false;
        }

        // Un chef de projet ne peut modifier que ses projets ; les profils
        // disposant de project.view_all peuvent modifier tout projet actif.
        if ($this->permissionChecker->has($user, 'project.view_all')) {
            return true;
        }

        return $this->isMemberOf($user, $project);
    }

    private function isMemberOf(User $user, Project $project): bool
    {
        $userId = $user->getIdString();
        if ('' === $userId) {
            return false;
        }

        if ($project->getManager()?->getIdString() === $userId) {
            return true;
        }

        foreach ($project->getMembers() as $member) {
            if ($member->getIdString() === $userId) {
                return true;
            }
        }

        return false;
    }
}
