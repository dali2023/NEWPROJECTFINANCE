<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MailSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailSettings>
 */
final class MailSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailSettings::class);
    }

    /** Fiche unique de paramétrage SMTP. */
    public function findSingleton(): ?MailSettings
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}