<?php

declare(strict_types=1);

namespace App\Repository\Trait;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Pagination des listes volumineuses (§9.1). Le décompte est effectué par
 * Doctrine (Paginator) afin de rester exact en présence de jointures.
 */
trait PaginatesQueries
{
    public const DEFAULT_PER_PAGE = 25;
    public const MAX_PER_PAGE = 200;
    /** Plafond spécifique aux exports Excel (les listes restent limitées à 200). */
    public const MAX_EXPORT_PER_PAGE = 10000;

    /**
     * @return array{items: list<mixed>, total: int, page: int, perPage: int, pages: int}
     */
    public function paginate(QueryBuilder $queryBuilder, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $perPage = max(1, $perPage);
        $perPage = min($perPage, $perPage > self::MAX_PER_PAGE ? self::MAX_EXPORT_PER_PAGE : self::MAX_PER_PAGE);
        $page = max(1, $page);

        $paginator = new Paginator($queryBuilder->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage), true);
        $total = \count($paginator);
        $pages = (int) max(1, (int) ceil($total / $perPage));

        if ($page > $pages) {
            $page = $pages;
            $paginator = new Paginator($queryBuilder->setFirstResult(($page - 1) * $perPage), true);
        }

        /** @var list<mixed> $items */
        $items = iterator_to_array($paginator);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
        ];
    }
}
