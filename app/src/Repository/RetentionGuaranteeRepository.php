<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\RetentionGuarantee;
use App\Enum\RetentionStatus;
use App\Service\Money;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RetentionGuarantee>
 */
class RetentionGuaranteeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RetentionGuarantee::class);
    }

    /** @return list<RetentionGuarantee> */
    public function findForContract(Contract $contract): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.contract = :contract')
            ->setParameter('contract', $contract)
            ->orderBy('r.eligibilityDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<RetentionGuarantee> */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('c', 'cl')
            ->leftJoin('r.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->where('r.status != :released')
            ->setParameter('released', RetentionStatus::Released)
            ->orderBy('r.eligibilityDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<RetentionGuarantee> */
    public function findEligible(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');

        return $this->createQueryBuilder('r')
            ->addSelect('c', 'cl')
            ->leftJoin('r.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->where('r.status != :released')
            ->andWhere('r.eligibilityDate IS NOT NULL')
            ->andWhere('r.eligibilityDate <= :today')
            ->setParameter('released', RetentionStatus::Released)
            ->setParameter('today', $today)
            ->orderBy('r.eligibilityDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumRemaining(): string
    {
        return Money::sum(array_map(
            static fn (RetentionGuarantee $guarantee): string => $guarantee->getRemainingToRelease(),
            $this->findPending(),
        ));
    }
}
