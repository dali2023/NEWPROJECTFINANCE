<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MaintenanceContract;
use App\Enum\MaintenanceRenewalStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MaintenanceContract>
 */
class MaintenanceContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceContract::class);
    }

    /**
     * Contrats de maintenance dont la date de préparation au renouvellement est
     * atteinte (calcul en PHP : le préavis peut être porté par le contrat).
     *
     * @return list<MaintenanceContract>
     */
    public function findDueForRenewal(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');

        $candidates = $this->createQueryBuilder('m')
            ->addSelect('c', 'cl')
            ->leftJoin('m.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->where('m.maintenanceEndDate IS NOT NULL')
            ->andWhere('m.maintenanceEndDate >= :today')
            ->andWhere('m.renewalStatus NOT IN (:closed)')
            ->setParameter('today', $today)
            ->setParameter('closed', [
                MaintenanceRenewalStatus::Renewed->value,
                MaintenanceRenewalStatus::NotRenewed->value,
                MaintenanceRenewalStatus::Terminated->value,
            ])
            ->orderBy('m.maintenanceEndDate', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $candidates,
            static function (MaintenanceContract $maintenance) use ($today): bool {
                $preparation = $maintenance->getRenewalPreparationDate();

                return null !== $preparation && $preparation <= $today;
            },
        ));
    }

    /** @return list<MaintenanceContract> */
    public function findActive(?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');

        return $this->createQueryBuilder('m')
            ->addSelect('c', 'cl')
            ->leftJoin('m.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->where('m.maintenanceStartDate IS NULL OR m.maintenanceStartDate <= :today')
            ->andWhere('m.maintenanceEndDate IS NULL OR m.maintenanceEndDate >= :today')
            ->setParameter('today', $today)
            ->orderBy('m.maintenanceEndDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
