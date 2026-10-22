<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * Permissions regroupées par module fonctionnel pour l'écran
     * d'administration des rôles (§7.12).
     *
     * @return array<string, list<Permission>>
     */
    public function findAllGroupedByModule(): array
    {
        /** @var list<Permission> $permissions */
        $permissions = $this->createQueryBuilder('p')
            ->orderBy('p.module', 'ASC')
            ->addOrderBy('p.code', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission->getModule() ?? 'Général'][] = $permission;
        }

        return $grouped;
    }

    public function findOneByCode(string $code): ?Permission
    {
        return $this->findOneBy(['code' => $code]);
    }
}
