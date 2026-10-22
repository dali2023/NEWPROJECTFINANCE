<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExpensePayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExpensePayment>
 */
class ExpensePaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpensePayment::class);
    }

    public function findForExpense(\App\Entity\Expense $expense): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.expense = :expense')
            ->setParameter('expense', $expense)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
