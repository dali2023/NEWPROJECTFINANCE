<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BankAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BankAccount>
 */
class BankAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankAccount::class);
    }

    /** @return list<BankAccount> */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.isActive = true')
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function totalBalance(): string
    {
        return \App\Service\Money::sum(array_map(
            static fn (BankAccount $account): string => $account->getBalance(),
            $this->findBy([], ['name' => 'ASC']),
        ));
    }
}
