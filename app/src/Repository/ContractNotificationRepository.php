<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContractNotification;
use App\Entity\ContractMilestone;
use App\Entity\User;
use App\Enum\ContractNotificationSeverity;
use App\Enum\ContractNotificationStatus;
use App\Enum\ContractNotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractNotification>
 */
class ContractNotificationRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractNotification::class);
    }

    /**
     * Centre de notifications — écran 17 (§15.8).
     *
     * @param array{
     *     type?: ContractNotificationType|null,
     *     status?: ContractNotificationStatus|null,
     *     severity?: ContractNotificationSeverity|null,
     *     recipient?: User|null,
     *     contract?: Contract|null,
     *     term?: string|null,
     *     activeOnly?: bool|null,
     *     scopeUser?: User|null,
     * } $filters
     *
     * @return array{items: list<ContractNotification>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->baseQueryBuilder();

        if (null !== ($filters['contract'] ?? null)) {
            $qb->andWhere('n.contract = :contract')->setParameter('contract', $filters['contract']);
        }

        if (null !== ($filters['type'] ?? null)) {
            $qb->andWhere('n.notificationType = :type')->setParameter('type', $filters['type']);
        }

        if (null !== ($filters['status'] ?? null)) {
            $qb->andWhere('n.status = :status')->setParameter('status', $filters['status']);
        }

        if (null !== ($filters['severity'] ?? null)) {
            $qb->andWhere('n.severity = :severity')->setParameter('severity', $filters['severity']);
        }

        if (null !== ($filters['recipient'] ?? null)) {
            $qb->andWhere('n.recipient = :recipient')->setParameter('recipient', $filters['recipient']);
        }

        if (true === ($filters['activeOnly'] ?? null)) {
            $qb->andWhere('n.status IN (:active)')
                ->setParameter('active', [ContractNotificationStatus::Pending->value, ContractNotificationStatus::InProgress->value]);
        }

        if (null !== ($filters['scopeUser'] ?? null)) {
            $qb->andWhere('n.recipient = :scopeUser OR c.responsible = :scopeUser')
                ->setParameter('scopeUser', $filters['scopeUser']);
        }

        $term = $filters['term'] ?? null;
        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(n.title) LIKE :term OR LOWER(c.reference) LIKE :term OR LOWER(c.title) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        return $this->paginate($qb, $page, $perPage);
    }

    private function baseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('n')
            ->addSelect('c', 'cl', 'u', 'm')
            ->leftJoin('n.contract', 'c')
            ->leftJoin('c.client', 'cl')
            ->leftJoin('n.recipient', 'u')
            ->leftJoin('n.milestone', 'm')
            ->orderBy('n.severity', 'DESC')
            ->addOrderBy('n.triggerDate', 'ASC');
    }

    public function findByUniqueKey(string $key): ?ContractNotification
    {
        return $this->findOneBy(['uniqueKey' => $key]);
    }

    /**
     * Alertes actives dont l'email de rappel reste à envoyer.
     *
     * @return list<ContractNotification>
     */
    public function findEmailCandidates(int $limit, \DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('n')
            ->addSelect('c', 'u')
            ->leftJoin('n.contract', 'c')
            ->leftJoin('n.recipient', 'u')
            ->where('n.status IN (:active)')
            ->andWhere('n.emailSentAt IS NULL')
            ->andWhere('n.emailAttempts < :maxAttempts')
            ->andWhere('n.triggerDate <= :today')
            ->andWhere('u.email IS NOT NULL')
            ->andWhere('u.email != :empty')
            ->setParameter('active', [ContractNotificationStatus::Pending->value, ContractNotificationStatus::InProgress->value])
            ->setParameter('maxAttempts', ContractNotification::MAX_EMAIL_ATTEMPTS)
            ->setParameter('today', $today->setTime(0, 0))
            ->setParameter('empty', '')
            ->orderBy('n.triggerDate', 'ASC')
            ->addOrderBy('n.severity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Réinitialise les tentatives d'envoi des rappels bloqués après trois
     * échecs, après correction de la configuration SMTP.
     */
    public function resetFailedEmailAttempts(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->update()
            ->set('n.emailAttempts', 0)
            ->set('n.emailError', 'NULL')
            ->set('n.emailLastAttemptAt', 'NULL')
            ->where('n.emailSentAt IS NULL')
            ->andWhere('n.emailError IS NOT NULL')
            ->getQuery()
            ->execute();
    }

    /** @return list<ContractNotification> */
    public function findActive(?\DateTimeImmutable $today = null, ?int $limit = null): array
    {
        $qb = $this->baseQueryBuilder()
            ->andWhere('n.status IN (:active)')
            ->setParameter('active', [ContractNotificationStatus::Pending->value, ContractNotificationStatus::InProgress->value]);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countActive(?User $scopeUser = null): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->leftJoin('n.contract', 'c')
            ->where('n.status IN (:active)')
            ->setParameter('active', [ContractNotificationStatus::Pending->value, ContractNotificationStatus::InProgress->value]);

        if (null !== $scopeUser) {
            $qb->andWhere('n.recipient = :scopeUser OR c.responsible = :scopeUser')
                ->setParameter('scopeUser', $scopeUser);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Clôt les alertes de facturation devenues obsolètes après report.
     */
    public function dismissForMilestone(ContractMilestone $milestone, string $reason): int
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.status', ':dismissed')
            ->set('n.resolvedAt', ':now')
            ->set('n.resolutionNote', ':reason')
            ->where('n.milestone = :milestone')
            ->andWhere('n.notificationType = :type')
            ->andWhere('n.status IN (:active)')
            ->setParameter('dismissed', ContractNotificationStatus::Dismissed->value)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('reason', $reason)
            ->setParameter('milestone', $milestone)
            ->setParameter('type', ContractNotificationType::BillingDue->value)
            ->setParameter('active', [ContractNotificationStatus::Pending->value, ContractNotificationStatus::InProgress->value]);

        return (int) $qb->getQuery()->execute();
    }

    /** @return list<ContractNotification> */
    public function findHistory(int $limit = 50): array
    {
        return $this->baseQueryBuilder()
            ->andWhere('n.status IN (:closed)')
            ->setParameter('closed', [ContractNotificationStatus::Resolved->value, ContractNotificationStatus::Dismissed->value])
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
