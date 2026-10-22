<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Contract;
use App\Entity\User;
use App\Security\PermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Habilitations portant sur un contrat précis (§15.9).
 *
 * La consultation est globale avec `contract.view_all`, ou limitée aux contrats
 * dont l'utilisateur est responsable, créateur ou membre du projet avec
 * `contract.view_own`. Les actions sensibles (validation, renouvellement,
 * facturation, notifications) disposent de permissions distinctes afin de
 * respecter la séparation des tâches.
 */
final class ContractVoter extends Voter
{
    public const VIEW = 'CONTRACT_VIEW';
    public const EDIT = 'CONTRACT_EDIT';
    public const VALIDATE = 'CONTRACT_VALIDATE';
    public const RENEW = 'CONTRACT_RENEW';
    public const BILL = 'CONTRACT_BILL';
    public const NOTIFY = 'CONTRACT_NOTIFY';

    public function __construct(private readonly PermissionChecker $permissionChecker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Contract
            && \in_array($attribute, [self::VIEW, self::EDIT, self::VALIDATE, self::RENEW, self::BILL, self::NOTIFY], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        /** @var Contract $contract */
        $contract = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($user, $contract),
            self::EDIT => $this->canEdit($user, $contract),
            self::VALIDATE => $this->canEdit($user, $contract)
                && $this->permissionChecker->has($user, 'contract.validate'),
            self::RENEW => $this->canView($user, $contract)
                && !$contract->getStatus()->isFrozen()
                && $this->permissionChecker->has($user, 'contract.renew'),
            self::BILL => $this->canView($user, $contract)
                && !$contract->getStatus()->isFrozen()
                && $this->permissionChecker->has($user, 'contract.bill'),
            self::NOTIFY => $this->canView($user, $contract)
                && $this->permissionChecker->has($user, 'contract.notify'),
            default => false,
        };
    }

    private function canView(User $user, Contract $contract): bool
    {
        if ($contract->isDeleted()) {
            return false;
        }

        if ($this->permissionChecker->has($user, 'contract.view_all')) {
            return true;
        }

        return $this->permissionChecker->has($user, 'contract.view_own')
            && $contract->isResponsibleFor($user);
    }

    private function canEdit(User $user, Contract $contract): bool
    {
        if ($contract->isDeleted() || $contract->getStatus()->isFrozen()) {
            return false;
        }

        if (!$this->permissionChecker->has($user, 'contract.manage')) {
            return false;
        }

        if ($this->permissionChecker->has($user, 'contract.view_all')) {
            return true;
        }

        return $this->permissionChecker->has($user, 'contract.view_own')
            && $contract->isResponsibleFor($user);
    }
}
