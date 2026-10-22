<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CashMovement;
use App\Enum\TreasuryDirection;
use App\Enum\TreasuryMovementType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashMovement>
 */
class CashMovementRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashMovement::class);
    }

    /**
     * @param array{term?: string|null, account?: object|null, direction?: TreasuryDirection|null, type?: TreasuryMovementType|null, reconciled?: bool|null} $filters
     *
     * @return array{items: list<CashMovement>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('a')
            ->leftJoin('m.bankAccount', 'a')
            ->orderBy('m.movementDate', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC');

        $term = $filters['term'] ?? null;
        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(m.reference) LIKE :term OR LOWER(m.label) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== ($filters['account'] ?? null)) {
            $qb->andWhere('m.bankAccount = :account')->setParameter('account', $filters['account']);
        }

        if (null !== ($filters['direction'] ?? null)) {
            $qb->andWhere('m.direction = :direction')->setParameter('direction', $filters['direction']);
        }

        if (null !== ($filters['type'] ?? null)) {
            $qb->andWhere('m.movementType = :type')->setParameter('type', $filters['type']);
        }

        if (null !== ($filters['reconciled'] ?? null)) {
            $qb->andWhere('m.reconciled = :reconciled')->setParameter('reconciled', $filters['reconciled']);
        }

        return $this->paginate($qb, $page, $perPage);
    }

    public function sum(TreasuryDirection $direction, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): string
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.amount), 0)')
            ->andWhere('m.direction = :direction')
            ->setParameter('direction', $direction);

        if (null !== $from) {
            $qb->andWhere('m.movementDate >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('m.movementDate <= :to')->setParameter('to', $to);
        }

        return \App\Service\Money::normalize((string) $qb->getQuery()->getSingleScalarResult());
    }
}
