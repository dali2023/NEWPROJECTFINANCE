<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\ContractMilestone;
use App\Entity\Project;
use App\Enum\CompletionStatus;
use App\Enum\ContractBillingStatus;
use App\Enum\MilestoneType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractMilestone>
 */
class ContractMilestoneRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractMilestone::class);
    }

    /**
     * Écran 16 — facturation à réaliser (§15.8).
     *
     * @param array{
     *     term?: string|null,
     *     contract?: Contract|null,
     *     project?: Project|null,
     *     billingStatus?: ContractBillingStatus|null,
     *     overdueOnly?: bool|null,
     *     readyOnly?: bool|null,
     *     onlyFor?: \App\Entity\User|null,
     * } $filters
     *
     * @return array{items: list<ContractMilestone>, total: int, page: int, perPage: int, pages: int}
     */
    public function searchBilling(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->baseBillingQuery();

        $term = $filters['term'] ?? null;
        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(m.reference) LIKE :term OR LOWER(m.label) LIKE :term OR LOWER(c.reference) LIKE :term OR LOWER(c.title) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== ($filters['contract'] ?? null)) {
            $qb->andWhere('m.contract = :contract')->setParameter('contract', $filters['contract']);
        }

        if (null !== ($filters['project'] ?? null)) {
            $qb->andWhere('m.project = :project')->setParameter('project', $filters['project']);
        }

        if (null !== ($filters['billingStatus'] ?? null)) {
            $qb->andWhere('m.billingStatus = :billingStatus')->setParameter('billingStatus', $filters['billingStatus']);
        }

        if (true === ($filters['readyOnly'] ?? null)) {
            $qb->andWhere('m.billingStatus = :ready')->setParameter('ready', ContractBillingStatus::ReadyToInvoice);
        }

        if (true === ($filters['overdueOnly'] ?? null)) {
            $this->applyOverdue($qb, new \DateTimeImmutable('today'));
        }

        if (null !== ($filters['onlyFor'] ?? null)) {
            $qb->andWhere('c.responsible = :scopeUser OR c.createdBy = :scopeUser OR p.manager = :scopeUser OR :scopeUser MEMBER OF p.members')
                ->setParameter('scopeUser', $filters['onlyFor']);
        }

        return $this->paginate($qb, $page, $perPage);
    }

    private function baseBillingQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->addSelect('c', 'cl', 'p', 'r')
            ->leftJoin('m.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->leftJoin('m.project', 'p')
            ->leftJoin('m.responsible', 'r')
            ->andWhere('m.completionStatus NOT IN (:blockedCompletion)')
            ->andWhere('m.billingStatus != :fullyInvoiced')
            ->setParameter('blockedCompletion', [CompletionStatus::Cancelled->value, CompletionStatus::Suspended->value])
            ->setParameter('fullyInvoiced', ContractBillingStatus::FullyInvoiced->value)
            ->orderBy('m.plannedDate', 'ASC')
            ->addOrderBy('m.reference', 'ASC');
    }

    private function applyOverdue(QueryBuilder $qb, \DateTimeImmutable $today): void
    {
        $qb->andWhere('m.plannedDate IS NOT NULL')
            ->andWhere('m.plannedDate < :today')
            ->andWhere('m.billingStatus != :blocked')
            ->setParameter('today', $today)
            ->setParameter('blocked', ContractBillingStatus::Blocked->value);
    }

    /** @return list<ContractMilestone> Échéances en retard (§15.10 D). */
    public function findOverdue(?\DateTimeImmutable $today = null, ?int $limit = null, ?Contract $contract = null): array
    {
        $qb = $this->baseBillingQuery();
        $this->applyOverdue($qb, $today ?? new \DateTimeImmutable('today'));

        if (null !== $contract) {
            $qb->andWhere('m.contract = :contract')->setParameter('contract', $contract);
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countOverdue(?\DateTimeImmutable $today = null, ?Contract $contract = null): int
    {
        return \count($this->findOverdue($today, null, $contract));
    }

    /** @return list<ContractMilestone> */
    public function findReadyToInvoice(?\DateTimeImmutable $today = null, ?int $limit = null): array
    {
        $qb = $this->baseBillingQuery()
            ->andWhere('m.billingStatus = :ready')
            ->setParameter('ready', ContractBillingStatus::ReadyToInvoice);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Revenus prévisionnels de maintenance (§15.10 E).
     *
     * @return list<ContractMilestone>
     */
    public function findForecastMaintenance(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $from ??= new \DateTimeImmutable('today');
        $qb = $this->createQueryBuilder('m')
            ->addSelect('c', 'cl')
            ->leftJoin('m.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->where('m.milestoneType IN (:types)')
            ->andWhere('m.completionStatus != :cancelled')
            ->andWhere('m.plannedDate IS NOT NULL')
            ->andWhere('m.plannedDate >= :from')
            ->setParameter('types', [MilestoneType::MaintenancePeriod->value, MilestoneType::AnnualRenewal->value])
            ->setParameter('cancelled', CompletionStatus::Cancelled->value)
            ->setParameter('from', $from)
            ->orderBy('m.plannedDate', 'ASC');

        if (null !== $to) {
            $qb->andWhere('m.plannedDate <= :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<ContractMilestone> */
    public function findUpcoming(?\DateTimeImmutable $today = null, int $limit = 8): array
    {
        $today ??= new \DateTimeImmutable('today');

        return $this->createQueryBuilder('m')
            ->addSelect('c', 'cl')
            ->leftJoin('m.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->where('m.plannedDate IS NOT NULL')
            ->andWhere('m.plannedDate >= :today')
            ->andWhere('m.completionStatus NOT IN (:blocked)')
            ->setParameter('today', $today)
            ->setParameter('blocked', [CompletionStatus::Cancelled->value, CompletionStatus::Completed->value])
            ->orderBy('m.plannedDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByGenerationKey(string $key): ?ContractMilestone
    {
        return $this->findOneBy(['generationKey' => $key]);
    }

    public function nextPositionForContract(Contract $contract): int
    {
        $max = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.contract = :contract')
            ->setParameter('contract', $contract)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
