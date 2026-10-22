<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProjectType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectType>
 */
class ProjectTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectType::class);
    }

    /** @return list<ProjectType> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ProjectType> */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = true')
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function codeExists(string $code, ?ProjectType $except = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('UPPER(t.code) = :code')
            ->setParameter('code', strtoupper(trim($code)));

        if (null !== $except) {
            $qb->andWhere('t.id != :except')->setParameter('except', $except->ensureId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
