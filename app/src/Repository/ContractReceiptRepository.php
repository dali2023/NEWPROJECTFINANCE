<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContractReceipt;
use App\Enum\ReceiptType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractReceipt>
 */
class ContractReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractReceipt::class);
    }

    /** @return list<ContractReceipt> */
    public function findPlanned(?ReceiptType $type = null, ?\DateTimeImmutable $until = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('c', 'cl')
            ->leftJoin('r.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->where('r.plannedDate IS NOT NULL')
            ->andWhere('r.actualDate IS NULL')
            ->orderBy('r.plannedDate', 'ASC');

        if (null !== $type) {
            $qb->andWhere('r.receiptType = :type')->setParameter('type', $type);
        }

        if (null !== $until) {
            $qb->andWhere('r.plannedDate <= :until')->setParameter('until', $until);
        }

        return $qb->getQuery()->getResult();
    }
}
