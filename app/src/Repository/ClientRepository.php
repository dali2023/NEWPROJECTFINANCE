<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use App\Enum\ClientType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * @return array{items: list<Client>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(
        ?string $term = null,
        ?ClientType $type = null,
        ?bool $activeOnly = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NULL')
            ->orderBy('c.name', \SortDirection::Ascending);

        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(c.name) LIKE :term OR LOWER(c.code) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== $type) {
            $qb->andWhere('c.clientType = :type')->setParameter('type', $type);
        }

        if (null !== $activeOnly) {
            $qb->andWhere('c.isActive = :active')->setParameter('active', $activeOnly);
        }

        return $this->paginate($qb, $page, $perPage);
    }

    public function codeExists(string $code, ?Client $except = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('UPPER(c.code) = :code')
            ->setParameter('code', strtoupper(trim($code)));

        if (null !== $except) {
            $qb->andWhere('c.id != :except')->setParameter('except', $except->ensureId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<Client> */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->andWhere('c.deletedAt IS NULL')
            ->orderBy('c.name', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isActive = true')
            ->andWhere('c.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
