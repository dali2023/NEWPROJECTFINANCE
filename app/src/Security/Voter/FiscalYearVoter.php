<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\FiscalYear;
use App\Entity\User;
use App\Security\PermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Verrouillage des exercices (§4.1, §5.4, REC-014).
 *
 * - WRITE : écriture ordinaire autorisée uniquement sur un exercice ouvert.
 * - REOPEN : réouverture d'un exercice clôturé, réservée à une permission
 *   spécifique et systématiquement tracée.
 */
final class FiscalYearVoter extends Voter
{
    public const WRITE = 'FISCAL_YEAR_WRITE';
    public const REOPEN = 'FISCAL_YEAR_REOPEN';

    public function __construct(private readonly PermissionChecker $permissionChecker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof FiscalYear && \in_array($attribute, [self::WRITE, self::REOPEN], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var FiscalYear $fiscalYear */
        $fiscalYear = $subject;

        return match ($attribute) {
            self::WRITE => $fiscalYear->acceptsOrdinaryWrites(),
            self::REOPEN => $this->permissionChecker->has($user, 'fiscal_year.reopen'),
            default => false,
        };
    }
}
