<?php

namespace EWZ\SymfonyAdminBundle\Pagerfanta\Adapter;

use Doctrine\ORM\Query;
use Pagerfanta\Adapter\FixedAdapter as BasedFixedAdapter;

class FixedAdapter extends BasedFixedAdapter
{
    /** @var Query */
    private $query;

    /**
     * @param int                $nbResults
     * @param array|\Traversable $results
     * @param Query              $query
     */
    public function __construct(int $nbResults, $results, Query $query)
    {
        parent::__construct($nbResults, $results);

        $this->query = $query;
    }

    /**
     * @return Query
     */
    public function getQuery(): Query
    {
        return $this->query;
    }
}
