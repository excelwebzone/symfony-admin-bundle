<?php

namespace EWZ\SymfonyAdminBundle\Repository\Traits;

use Doctrine\ORM\Query;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;

trait PagerfantaTrait
{
    /**
     * @param Query $query
     * @param int   $page
     * @param int   $limit
     *
     * @return Pagerfanta
     */
    protected function createPaginator(Query $query, int $page, int $limit): Pagerfanta
    {
        $paginator = new Pagerfanta(new DoctrineORMAdapter($query));
        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage($page);

        return $paginator;
    }
}
