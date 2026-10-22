<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Contract;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ContractStatus;
use App\Enum\ContractType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contract>
 */
class ContractRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    /**
     * Registre des contrats — écran 13 (§15.8).
     *
     * @param array{
     *     term?: string|null,
     *     client?: Client|null,
     *     type?: ContractType|null,
     *     project?: Project|null,
     *     responsible?: User|null,
     *     status?: ContractStatus|null,
     *     expiryWithin?: int|null,
     *     expired?: bool|null,
     *     onlyFor?: User|null,
     * } $filters
     *
     * @return array{items: list<Contract>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('cl', 'p', 'r', 'm')
            ->leftJoin('c.client', 'cl')
            ->leftJoin('c.project', 'p')
            ->leftJoin('c.responsible', 'r')
            ->leftJoin('c.maintenanceContracts', 'm')
            ->andWhere('c.deletedAt IS NULL')
            ->orderBy('c.effectiveEndDate', 'ASC')
            ->addOrderBy('c.reference', 'DESC');

        $term = $filters['term'] ?? null;
        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(c.reference) LIKE :term OR LOWER(c.title) LIKE :term OR LOWER(c.marketReference) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== ($filters['client'] ?? null)) {
            $qb->andWhere('c.client = :client')->setParameter('client', $filters['client']);
        }

        if (null !== ($filters['type'] ?? null)) {
            $qb->andWhere('c.contractType = :type')->setParameter('type', $filters['type']);
        }

        if (null !== ($filters['project'] ?? null)) {
            $qb->andWhere('c.project = :project')->setParameter('project', $filters['project']);
        }

        if (null !== ($filters['responsible'] ?? null)) {
            $qb->andWhere('c.responsible = :responsible')->setParameter('responsible', $filters['responsible']);
        }

        if (null !== ($filters['status'] ?? null)) {
            $this->applyStatusFilter($qb, $filters['status']);
        }

        $today = new \DateTimeImmutable('today');
        $expiryWithin = $filters['expiryWithin'] ?? null;
        if (null !== $expiryWithin) {
            $qb->andWhere('c.effectiveEndDate IS NOT NULL')
                ->andWhere('c.effectiveEndDate >= :today')
                ->andWhere('c.effectiveEndDate <= :expiryLimit')
                ->setParameter('today', $today)
                ->setParameter('expiryLimit', $today->modify(sprintf('+%d days', max(0, $expiryWithin))));
        }

        if (true === ($filters['expired'] ?? null)) {
            $qb->andWhere('c.effectiveEndDate IS NOT NULL')
                ->andWhere('c.effectiveEndDate < :today')
                ->setParameter('today', $today);
        }

        if (null !== ($filters['onlyFor'] ?? null)) {
            $qb->andWhere('c.responsible = :scopeUser OR c.createdBy = :scopeUser OR p.manager = :scopeUser OR :scopeUser MEMBER OF p.members')
                ->setParameter('scopeUser', $filters['onlyFor']);
        }

        return $this->paginate($qb, $page, $perPage);
    }

    /**
     * Filtre de statut. Les statuts calculés à partir des dates sont traduits
     * en critères de date, sans jamais déclencher de renouvellement.
     */
    private function applyStatusFilter(QueryBuilder $qb, ContractStatus $status): void
    {
        $today = new \DateTimeImmutable('today');

        switch ($status) {
            case ContractStatus::Expired:
                $qb->andWhere('c.effectiveEndDate IS NOT NULL')
                    ->andWhere('c.effectiveEndDate < :today')
                    ->andWhere('c.status NOT IN (:frozen)')
                    ->setParameter('today', $today)
                    ->setParameter('frozen', [ContractStatus::Closed->value, ContractStatus::Terminated->value]);
                break;
            case ContractStatus::Expiring:
                $qb->andWhere('c.effectiveEndDate IS NOT NULL')
                    ->andWhere('c.effectiveEndDate >= :today')
                    ->andWhere('c.effectiveEndDate <= :limit')
                    ->setParameter('today', $today)
                    ->setParameter('limit', $today->modify('+90 days'));
                break;
            case ContractStatus::ToRenew:
                $qb->andWhere('c.effectiveEndDate IS NOT NULL')
                    ->andWhere('c.effectiveEndDate >= :today')
                    ->andWhere('c.effectiveEndDate <= :limit')
                    ->setParameter('today', $today)
                    ->setParameter('limit', $today->modify('+180 days'));
                break;
            default:
                $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }
    }

    /** @return list<Contract> Contrats expirant dans N jours (REC-031). */
    public function findExpiringWithin(int $days, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');

        return $this->createQueryBuilder('c')
            ->addSelect('cl', 'r')
            ->leftJoin('c.client', 'cl')
            ->leftJoin('c.responsible', 'r')
            ->where('c.deletedAt IS NULL')
            ->andWhere('c.effectiveEndDate IS NOT NULL')
            ->andWhere('c.effectiveEndDate >= :today')
            ->andWhere('c.effectiveEndDate <= :limit')
            ->andWhere('c.status NOT IN (:frozen)')
            ->setParameter('today', $today)
            ->setParameter('limit', $today->modify(sprintf('+%d days', max(0, $days))))
            ->setParameter('frozen', [ContractStatus::Closed->value, ContractStatus::Terminated->value])
            ->orderBy('c.effectiveEndDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Contract> */
    public function findActiveWithDates(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('cl', 'r', 'm')
            ->leftJoin('c.client', 'cl')
            ->leftJoin('c.responsible', 'r')
            ->leftJoin('c.maintenanceContracts', 'm')
            ->where('c.deletedAt IS NULL')
            ->andWhere('c.status NOT IN (:frozen)')
            ->setParameter('frozen', [ContractStatus::Closed->value, ContractStatus::Terminated->value])
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        /** @var list<array{status: ContractStatus, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.status AS status', 'COUNT(c.id) AS total')
            ->where('c.deletedAt IS NULL')
            ->groupBy('c.status')
            ->getQuery()
            ->getResult();

        $counts = array_fill_keys(array_map(static fn (ContractStatus $s): string => $s->value, ContractStatus::cases()), 0);
        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['total'];
        }

        return $counts;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countExpiringWithin(int $days, ?\DateTimeImmutable $today = null): int
    {
        return \count($this->findExpiringWithin($days, $today));
    }

    /** Contrats dont la date de préparation au renouvellement est atteinte. */
    public function countRenewalsToPrepare(?\DateTimeImmutable $today = null): int
    {
        $today ??= new \DateTimeImmutable('today');
        $count = 0;
        foreach ($this->findActiveWithDates() as $contract) {
            $preparation = $contract->getRenewalPreparationDate();
            if (null !== $preparation && $preparation <= $today && null !== $contract->getEffectiveEndDate() && $contract->getEffectiveEndDate() >= $today) {
                ++$count;
            }
        }

        return $count;
    }

    /** @return list<Contract> */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('cl', 'r')
            ->leftJoin('c.client', 'cl')
            ->leftJoin('c.responsible', 'r')
            ->where('c.deletedAt IS NULL')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
