<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContractMilestoneHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractMilestoneHistory>
 */
class ContractMilestoneHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractMilestoneHistory::class);
    }
}
