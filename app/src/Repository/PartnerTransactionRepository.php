<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Partner;
use App\Entity\PartnerTransaction;
use App\Enum\PartnerTransactionStatus;
use App\Enum\PartnerTransactionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PartnerTransaction>
 */
class PartnerTransactionRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerTransaction::class);
    }

    /** @return list<PartnerTransaction> */
    public function findForPartner(Partner $partner): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.partner = :partner')
            ->setParameter('partner', $partner)
            ->orderBy('t.transactionDate', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function search(?PartnerTransactionType $type = null, ?PartnerTransactionStatus $status = null, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('p')
            ->leftJoin('t.partner', 'p')
            ->orderBy('t.transactionDate', 'DESC');

        if (null !== $type) {
            $qb->andWhere('t.transactionType = :type')->setParameter('type', $type);
        }
        if (null !== $status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        return $this->paginate($qb, $page, $perPage);
    }
}
