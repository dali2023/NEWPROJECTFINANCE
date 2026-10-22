<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Recherche d'un compte par nom de connexion ou adresse électronique,
     * insensible à la casse (§4.2 : l'unicité ne dépend pas de la casse).
     */
    public function findOneByIdentifier(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.username) = :identifier')
            ->orWhere('LOWER(u.email) = :identifier')
            ->setParameter('identifier', mb_strtolower(trim($identifier)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function identifierExists(string $identifier, ?User $except = null): bool
    {
        $found = $this->findOneByIdentifier($identifier);

        return null !== $found && $found->getId() !== $except?->getId();
    }

    /** @return array{items: list<User>, total: int, page: int, perPage: int, pages: int} */
    public function search(?string $term, ?bool $activeOnly, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.fullName', \SortDirection::Ascending);

        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(u.username) LIKE :term OR LOWER(u.email) LIKE :term OR LOWER(u.fullName) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (true === $activeOnly) {
            $qb->andWhere('u.isActive = true');
        }

        return $this->paginate($qb, $page, $perPage);
    }

    /** @return list<User> */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isActive = true')
            ->orderBy('u.fullName', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /** Un administrateur actif doit toujours subsister (§3.1). */
    public function countActiveAdministrators(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->join('u.roles', 'r')
            ->where('r.code = :admin')
            ->andWhere('u.isActive = true')
            ->setParameter('admin', 'ROLE_ADMIN')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Type de compte non supporté : %s', $user::class));
        }

        $user->setPasswordHash($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
