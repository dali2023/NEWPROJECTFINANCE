<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\ProjectType;
use App\Entity\User;
use App\Enum\ProjectStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Recherche filtrée (§7.3).
     *
     * @param array{
     *     term?: string|null,
     *     client?: Client|null,
     *     type?: ProjectType|null,
     *     status?: ProjectStatus|null,
     *     manager?: User|null,
     *     onlyFor?: User|null,
     *     contractualOnly?: bool|null,
     * } $filters
     *
     * @return array{items: list<Project>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->baseQueryBuilder();

        $term = $filters['term'] ?? null;
        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(p.name) LIKE :term OR LOWER(p.code) LIKE :term OR LOWER(p.contractReference) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== ($filters['client'] ?? null)) {
            $qb->andWhere('p.client = :client')->setParameter('client', $filters['client']);
        }

        if (null !== ($filters['type'] ?? null)) {
            $qb->andWhere('p.projectType = :type')->setParameter('type', $filters['type']);
        }

        if (null !== ($filters['status'] ?? null)) {
            $qb->andWhere('p.status = :status')->setParameter('status', $filters['status']);
        }

        if (null !== ($filters['manager'] ?? null)) {
            $qb->andWhere('p.manager = :manager')->setParameter('manager', $filters['manager']);
        }

        if (true === ($filters['contractualOnly'] ?? null)) {
            $qb->andWhere('p.status IN (:contractual)')
                ->setParameter('contractual', array_map(
                    static fn (ProjectStatus $status): string => $status->value,
                    array_filter(ProjectStatus::cases(), static fn (ProjectStatus $status): bool => $status->isContractual()),
                ));
        }

        // Portée projet : un chef de projet ne voit que ses projets (§3.5, REC-015).
        if (null !== ($filters['onlyFor'] ?? null)) {
            $qb->andWhere('p.manager = :scopeUser OR :scopeUser MEMBER OF p.members')
                ->setParameter('scopeUser', $filters['onlyFor']);
        }

        return $this->paginate($qb, $page, $perPage);
    }

    /** @return QueryBuilder */
    private function baseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c', 't', 'm')
            ->leftJoin('p.client', 'c')
            ->leftJoin('p.projectType', 't')
            ->leftJoin('p.manager', 'm')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.code', 'DESC');
    }

    public function codeExists(string $code, ?Project $except = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('UPPER(p.code) = :code')
            ->setParameter('code', strtoupper(trim($code)));

        if (null !== $except) {
            $qb->andWhere('p.id != :except')->setParameter('except', $except->ensureId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function existsWithCode(string $code): bool
    {
        return $this->codeExists($code);
    }

    /**
     * Somme des montants contractuels HT des projets considérés comme contrats
     * signés (§4.3, §8.1) : les opportunités et projets perdus sont exclus.
     */
    public function sumContractualAmountHt(): string
    {
        $statuses = array_map(
            static fn (ProjectStatus $status): string => $status->value,
            array_filter(ProjectStatus::cases(), static fn (ProjectStatus $status): bool => $status->isContractual()),
        );

        $result = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.contractAmountHt), 0)')
            ->where('p.status IN (:statuses)')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) $result;
    }

    /**
     * Nombre de projets par statut, pour le tableau de bord.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var list<array{status: ProjectStatus, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.status AS status', 'COUNT(p.id) AS total')
            ->where('p.deletedAt IS NULL')
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach (ProjectStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }
        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['total'];
        }

        return $counts;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Échéances à venir : jalons planifiés sur la période observée (§7.2). */
    public function findUpcomingFor(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.manager = :user OR :user MEMBER OF p.members')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('p.code', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }
}
