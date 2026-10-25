<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Prospect;
use App\Entity\User;
use App\Enum\ProspectStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prospect>
 */
class ProspectRepository extends ServiceEntityRepository
{
    use Trait\PaginatesQueries;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prospect::class);
    }

    /**
     * @param array{
     *     term?: string|null,
     *     status?: ProspectStatus|null,
     *     owner?: User|null,
     *     source?: string|null,
     * } $filters
     *
     * @return array{items: list<Prospect>, total: int, page: int, perPage: int, pages: int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('o')
            ->leftJoin('p.owner', 'o')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.reference', 'DESC');

        $term = $filters['term'] ?? null;
        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere(
                'LOWER(p.name) LIKE :term
                 OR LOWER(p.reference) LIKE :term
                 OR LOWER(p.contactName) LIKE :term
                 OR LOWER(p.email) LIKE :term
                 OR LOWER(p.city) LIKE :term'
            )->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== ($filters['status'] ?? null)) {
            $qb->andWhere('p.status = :status')->setParameter('status', $filters['status']);
        }

        if (null !== ($filters['owner'] ?? null)) {
            $qb->andWhere('p.owner = :owner')->setParameter('owner', $filters['owner']);
        }

        $source = $filters['source'] ?? null;
        if (null !== $source && '' !== trim($source)) {
            $qb->andWhere('LOWER(p.source) LIKE :source')
                ->setParameter('source', '%'.mb_strtolower(trim($source)).'%');
        }

        return $this->paginate($qb, $page, $perPage);
    }

    /**
     * Recherche le prospect à mettre à jour lors d'un import.
     *
     * La priorité est donnée à l'adresse électronique (insensible à la casse).
     * En l'absence d'email, l'identité est recherchée sur le nom et la ville.
     */
    public function findDuplicate(?string $email, string $name, ?string $city): ?Prospect
    {
        $email = null === $email ? null : mb_strtolower(trim($email));
        if (null !== $email && '' !== $email) {
            $result = $this->createQueryBuilder('p')
                ->where('p.deletedAt IS NULL')
                ->andWhere('LOWER(p.email) = :email')
                ->setParameter('email', $email)
                ->orderBy('p.createdAt', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($result instanceof Prospect) {
                return $result;
            }
        }

        $name = trim($name);
        if ('' === $name) {
            return null;
        }

        $city = null === $city ? '' : trim($city);
        $qb = $this->createQueryBuilder('p')
            ->where('p.deletedAt IS NULL')
            ->andWhere('LOWER(p.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->orderBy('p.createdAt', 'ASC')
            ->setMaxResults(1);

        if ('' === $city) {
            $qb->andWhere('(p.city IS NULL OR p.city = :emptyCity)')
                ->setParameter('emptyCity', '');
        } else {
            $qb->andWhere('LOWER(p.city) = :city')
                ->setParameter('city', mb_strtolower($city));
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result instanceof Prospect ? $result : null;
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.deletedAt IS NULL')
            ->andWhere('p.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', [
                ProspectStatus::Converted->value,
                ProspectStatus::Lost->value,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
