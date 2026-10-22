<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\ContractRenewal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractRenewal>
 */
class ContractRenewalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractRenewal::class);
    }

    /** @return list<ContractRenewal> */
    public function findForContract(Contract $contract): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('c', 'p', 'n', 'u')
            ->leftJoin('r.contract', 'c')
            ->leftJoin('r.previousContract', 'p')
            ->leftJoin('r.newContract', 'n')
            ->leftJoin('r.responsible', 'u')
            ->where('r.contract = :contract OR r.previousContract = :contract OR r.newContract = :contract')
            ->setParameter('contract', $contract)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ContractRenewal> */
    public function findHistory(int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('c', 'p', 'n', 'u')
            ->leftJoin('r.contract', 'c')
            ->leftJoin('r.previousContract', 'p')
            ->leftJoin('r.newContract', 'n')
            ->leftJoin('r.responsible', 'u')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
