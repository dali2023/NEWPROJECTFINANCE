<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FiscalYear;
use App\Enum\FiscalYearStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FiscalYear>
 */
class FiscalYearRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FiscalYear::class);
    }

    /** @return list<FiscalYear> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.startDate', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    public function findCurrent(\DateTimeImmutable $on = new \DateTimeImmutable('today')): ?FiscalYear
    {
        return $this->createQueryBuilder('f')
            ->where('f.startDate <= :on')
            ->andWhere('f.endDate >= :on')
            ->setParameter('on', $on)
            ->orderBy('f.startDate', \SortDirection::Descending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOpen(): ?FiscalYear
    {
        return $this->findOneBy(['status' => FiscalYearStatus::Open], ['startDate' => 'DESC']);
    }

    /** Détecte un chevauchement de périodes (§5.4). */
    public function hasOverlap(\DateTimeImmutable $start, \DateTimeImmutable $end, ?FiscalYear $except = null): bool
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.startDate <= :end')
            ->andWhere('f.endDate >= :start')
            ->setParameter('end', $end)
            ->setParameter('start', $start);

        if (null !== $except) {
            $qb->andWhere('f.id != :except')->setParameter('except', $except->ensureId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
