<?php

namespace EWZ\SymfonyAdminBundle\Repository\Traits;

use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;

trait PagerfantaTrait
{
    /**
     * @param QueryBuilder $queryBuilder
     * @param int          $page
     * @param int          $limit
     *
     * @return Pagerfanta
     */
    protected function createPaginator(QueryBuilder $queryBuilder, int $page, int $limit): Pagerfanta
    {
        $paginator = new Pagerfanta(new DoctrineORMAdapter($queryBuilder));
        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage($page);

        return $paginator;
    }
}
