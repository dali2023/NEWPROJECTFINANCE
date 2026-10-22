<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Expense>
 */
class ExpenseRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * @param array{term?: string|null, supplier?: object|null, project?: object|null, status?: ExpenseStatus|null} $filters
     *
     * @return array{items: list<Expense>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->createQueryBuilder('e')
            ->addSelect('s', 'p')
            ->leftJoin('e.supplier', 's')
            ->leftJoin('e.project', 'p')
            ->orderBy('e.expenseDate', 'DESC')
            ->addOrderBy('e.reference', 'DESC');

        $term = $filters['term'] ?? null;
        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(e.reference) LIKE :term OR LOWER(e.label) LIKE :term OR LOWER(s.name) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== ($filters['supplier'] ?? null)) {
            $qb->andWhere('e.supplier = :supplier')->setParameter('supplier', $filters['supplier']);
        }

        if (null !== ($filters['project'] ?? null)) {
            $qb->andWhere('e.project = :project')->setParameter('project', $filters['project']);
        }

        if (null !== ($filters['status'] ?? null)) {
            $qb->andWhere('e.status = :status')->setParameter('status', $filters['status']);
        }

        return $this->paginate($qb, $page, $perPage);
    }

    public function sumApproved(): string
    {
        return \App\Service\Money::sum(array_map(
            static fn (Expense $expense): string => $expense->getAmountTtc(),
            $this->createQueryBuilder('e')
                ->where('e.status IN (:statuses)')
                ->setParameter('statuses', [ExpenseStatus::Approved, ExpenseStatus::PartiallyPaid, ExpenseStatus::Paid])
                ->getQuery()
                ->getResult(),
        ));
    }

    public function sumPending(): string
    {
        return \App\Service\Money::sum(array_map(
            static fn (Expense $expense): string => $expense->getAmountTtc(),
            $this->createQueryBuilder('e')
                ->where('e.status IN (:statuses)')
                ->setParameter('statuses', [ExpenseStatus::Draft, ExpenseStatus::Submitted])
                ->getQuery()
                ->getResult(),
        ));
    }
}
