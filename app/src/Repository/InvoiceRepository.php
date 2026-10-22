<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * @param array{term?: string|null, client?: object|null, status?: InvoiceStatus|null, type?: InvoiceType|null, overdueOnly?: bool|null} $filters
     *
     * @return array{items: list<Invoice>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->createQueryBuilder('i')
            ->addSelect('c', 'p')
            ->leftJoin('i.client', 'c')
            ->leftJoin('i.project', 'p')
            ->orderBy('i.issueDate', 'DESC')
            ->addOrderBy('i.number', 'DESC');

        $term = $filters['term'] ?? null;
        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(i.number) LIKE :term OR LOWER(c.name) LIKE :term OR LOWER(i.notes) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== ($filters['client'] ?? null)) {
            $qb->andWhere('i.client = :client')->setParameter('client', $filters['client']);
        }

        if (null !== ($filters['status'] ?? null)) {
            $qb->andWhere('i.status = :status')->setParameter('status', $filters['status']);
        }

        if (null !== ($filters['type'] ?? null)) {
            $qb->andWhere('i.invoiceType = :type')->setParameter('type', $filters['type']);
        }

        if (true === ($filters['overdueOnly'] ?? null)) {
            $this->applyOverdue($qb, new \DateTimeImmutable('today'));
        }

        return $this->paginate($qb, $page, $perPage);
    }

    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('c')
            ->leftJoin('i.client', 'c')
            ->orderBy('i.issueDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function sumOpenBalance(): string
    {
        return \App\Service\Money::sum(array_map(
            static fn (Invoice $invoice): string => $invoice->getBalance(),
            $this->createQueryBuilder('i')
                ->where('i.status IN (:statuses)')
                ->setParameter('statuses', [InvoiceStatus::Validated, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
                ->getQuery()
                ->getResult(),
        ));
    }

    public function sumOverdueBalance(?\DateTimeImmutable $today = null): string
    {
        $today ??= new \DateTimeImmutable('today');
        $qb = $this->createQueryBuilder('i')
            ->where('i.status IN (:statuses)')
            ->andWhere('i.dueDate < :today')
            ->setParameter('statuses', [InvoiceStatus::Validated, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
            ->setParameter('today', $today);

        return \App\Service\Money::sum(array_map(
            static fn (Invoice $invoice): string => $invoice->getBalance(),
            $qb->getQuery()->getResult(),
        ));
    }

    private function applyOverdue(QueryBuilder $qb, \DateTimeImmutable $today): void
    {
        $qb->andWhere('i.status IN (:overdueStatuses)')
            ->andWhere('i.dueDate < :overdueToday')
            ->andWhere('i.amountTtc > i.paidAmount')
            ->setParameter('overdueStatuses', [InvoiceStatus::Validated, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
            ->setParameter('overdueToday', $today);
    }
}
