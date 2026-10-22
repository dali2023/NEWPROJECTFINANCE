<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectMilestone;
use App\Enum\MilestoneStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectMilestone>
 */
class ProjectMilestoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectMilestone::class);
    }

    /** @return list<ProjectMilestone> */
    public function findByProjectOrdered(Project $project): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.project = :project')
            ->setParameter('project', $project)
            ->orderBy('m.position', \SortDirection::Ascending)
            ->addOrderBy('m.plannedDate', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Prochaines échéances contractuelles (§7.4, onglet « Contrat et
     * échéancier ») : jalons non encore encaissés.
     *
     * @return list<ProjectMilestone>
     */
    public function findUpcoming(\DateTimeImmutable $from, ?\DateTimeImmutable $to = null, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('p')
            ->join('m.project', 'p')
            ->where('m.plannedDate >= :from')
            ->andWhere('m.status IN (:statuses)')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('from', $from)
            ->setParameter('statuses', [MilestoneStatus::Planned->value, MilestoneStatus::Reached->value, MilestoneStatus::Invoiced->value])
            ->orderBy('m.plannedDate', \SortDirection::Ascending)
            ->setMaxResults($limit);

        if (null !== $to) {
            $qb->andWhere('m.plannedDate <= :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }
}
