<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public const ACTIONS_SENSIBLES = [
        'validate',
        'reject',
        'cancel',
        'close',
        'reopen',
        'deny',
        'delete',
        'login_failed',
        'permission_change',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Consultation du journal d'audit (§7.12, REC-018).
     *
     * @return array{items: list<AuditLog>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(
        ?string $entity = null,
        ?string $action = null,
        ?string $username = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        int $page = 1,
        int $perPage = 50,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('u')
            ->leftJoin('a.user', 'u')
            ->orderBy('a.occurredAt', \SortDirection::Descending);

        if (null !== $entity && '' !== trim($entity)) {
            $qb->andWhere('a.entity = :entity')->setParameter('entity', $entity);
        }

        if (null !== $action && '' !== trim($action)) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }

        if (null !== $username && '' !== trim($username)) {
            $qb->andWhere('LOWER(a.username) LIKE :username')
                ->setParameter('username', '%'.mb_strtolower(trim($username)).'%');
        }

        if (null !== $from) {
            $qb->andWhere('a.occurredAt >= :from')->setParameter('from', $from);
        }

        if (null !== $to) {
            $qb->andWhere('a.occurredAt <= :to')->setParameter('to', $to);
        }

        return $this->paginate($qb, $page, $perPage);
    }

    /** @return list<AuditLog> */
    public function findForEntity(string $entity, string $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('u')
            ->leftJoin('a.user', 'u')
            ->where('a.entity = :entity')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entity', $entity)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.occurredAt', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
