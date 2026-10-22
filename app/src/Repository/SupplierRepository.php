<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Supplier>
 */
class SupplierRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    /**
     * @return array{items: list<Supplier>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(
        ?string $term = null,
        ?bool $activeOnly = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->orderBy('s.name', \SortDirection::Ascending);

        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(s.name) LIKE :term OR LOWER(s.code) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== $activeOnly) {
            $qb->andWhere('s.isActive = :active')->setParameter('active', $activeOnly);
        }

        return $this->paginate($qb, $page, $perPage);
    }

    public function codeExists(string $code, ?Supplier $except = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('UPPER(s.code) = :code')
            ->setParameter('code', strtoupper(trim($code)));

        if (null !== $except) {
            $qb->andWhere('s.id != :except')->setParameter('except', $except->ensureId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<Supplier> */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->andWhere('s.deletedAt IS NULL')
            ->orderBy('s.name', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
