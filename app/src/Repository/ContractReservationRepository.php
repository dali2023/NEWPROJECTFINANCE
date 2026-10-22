<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContractReservation;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractReservation>
 */
class ContractReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractReservation::class);
    }

    /** @return list<ContractReservation> */
    public function findOpen(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('rc', 'c', 'cl', 'u')
            ->leftJoin('r.receipt', 'rc')
            ->leftJoin('rc.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->leftJoin('r.responsible', 'u')
            ->where('r.status = :status')
            ->setParameter('status', ReservationStatus::Open)
            ->orderBy('r.plannedResolutionDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', ReservationStatus::Open)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ContractReservation> */
    public function findDue(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');

        return $this->createQueryBuilder('r')
            ->addSelect('rc', 'c')
            ->leftJoin('r.receipt', 'rc')
            ->leftJoin('rc.contract', 'c')
            ->where('r.status = :status')
            ->andWhere('r.plannedResolutionDate IS NOT NULL')
            ->andWhere('r.plannedResolutionDate <= :today')
            ->setParameter('status', ReservationStatus::Open)
            ->setParameter('today', $today)
            ->orderBy('r.plannedResolutionDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
