<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompanySettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanySettings>
 */
final class CompanySettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanySettings::class);
    }

    /** Fiche unique de paramétrage de la société. */
    public function findSingleton(): ?CompanySettings
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}