<?php

namespace EWZ\SymfonyAdminBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use EWZ\SymfonyAdminBundle\Model\User;
use EWZ\SymfonyAdminBundle\Pagerfanta\Adapter\FixedAdapter;
use EWZ\SymfonyAdminBundle\Repository\Traits\PagerfantaTrait;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @method Repo|null find($id, $lockMode = null, $lockVersion = null)
 * @method Repo|null findOneBy(array $criteria, array $orderBy = null)
 * @method Repo[]    findAll()
 * @method Repo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
abstract class AbstractRepository extends ServiceEntityRepository
{
    use PagerfantaTrait;

    const DEFAULT_LIMIT = 20;

    /** @var string */
    protected $className = null;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    /**
     * @param RegistryInterface     $registry
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(RegistryInterface $registry, TokenStorageInterface $tokenStorage)
    {
        parent::__construct($registry, $this->className);

        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        $class = $this->className;

        if (false !== strpos($class, ':')) {
            $metadata = $this->getClassMetadata($class);
            $class = $metadata->getName();
        }

        return $class;
    }

    /**
     * @param bool $includeAssociationNames
     *
     * @return array
     */
    public function getFieldNames(bool $includeAssociationNames = true): array
    {
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->getClassMetadata($this->getClass());

        /** @var array $fieldNames */
        $fieldNames = $classMetadata->getFieldNames();

        foreach ($classMetadata->getAssociationNames() as $assocName) {
            if ($classMetadata->isCollectionValuedAssociation($assocName)) {
                continue;
            }

            $fieldNames[] = $assocName;
        }

        return $fieldNames;
    }

    /**
     * @return mixed
     */
    public function create()
    {
        $class = $this->getClass();
        $object = new $class();

        return $object;
    }

    /**
     * @param mixed $object
     * @param bool  $andFlush
     */
    public function update($object, $andFlush = true): void
    {
        $this->getEntityManager()->persist($object);
        if ($andFlush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param mixed $object
     * @param bool  $andFlush
     */
    public function remove($object, $andFlush = true): void
    {
        $this->getEntityManager()->remove($object);
        if ($andFlush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param array $criteria
     *
     * @return int
     */
    public function countSearch(array $criteria): int
    {
        return $this->search($criteria, 1, null, null, true);
    }

    /**
     * @param array       $criteria
     * @param int         $page
     * @param int|null    $limit
     * @param string|null $sort
     * @param bool        $doCount
     *
     * @return Pagerfanta|\Traversable|int|bool
     */
    public function search(array $criteria, int $page = 1, int $limit = null, string $sort = null, bool $doCount = false)
    {
        $queryBuilder = $this->createQueryBuilder('q');

        $this->applyCriteria($queryBuilder, $criteria);
        $sort = $this->applySort($queryBuilder, $sort);

        if ($sort) {
            $alias = null;
            if (2 === substr_count($sort, '-')) {
                list($sortBy, $sortDir, $alias) = explode('-', $sort, 3);
            } else {
                list($sortBy, $sortDir) = explode('-', $sort, 2);
            }

            $queryBuilder = $queryBuilder->orderBy(sprintf('%s.%s', $alias ?: $queryBuilder->getRootAlias(), StringUtil::camelize($sortBy)), $sortDir);
        }

        if ($doCount) {
            return (int) $queryBuilder
                ->select('COUNT(1)')
                ->getQuery()
                ->getSingleScalarResult()
            ;
        }

        // get all
        if (-1 === $page) {
            return $queryBuilder->getQuery()->getResult();
        }

        if (is_null($limit)) {
            $limit = self::DEFAULT_LIMIT;
        }

        try {
            return $this->createPaginator($queryBuilder, $page, $limit);
        } catch (OutOfRangeCurrentPageException $e) {
            return false;
        }
    }

    /**
     * @param array $columns
     * @param Query $query
     *
     * @return array
     */
    public function getSearchTotals(array $columns, Query $query): array
    {
        // mock parse function on $query to retrive SQL column names
        $reflectionClass = new \ReflectionClass(Query::class);
        $reflectionMethod = $reflectionClass->getMethod('_parse');
        $reflectionMethod->setAccessible(true);

        /** @var ParserResult $parser */
        $parser = $reflectionMethod->invoke($query);

        /** @var array $scalarMappings */
        $scalarMappings = $parser->getResultSetMapping()->scalarMappings +
                            $parser->getResultSetMapping()->fieldMappings;

        // get total rows
        $rsm = new ResultSetMapping();

        foreach ($columns as $key => $value) {
            $column = $value['column'];
            $useSum = $value['useSum'];

            // get column name
            if (!$columnName = array_search($column, $scalarMappings)) {
                // replace field in column formula
                foreach ($scalarMappings as $alias => $field) {
                    $column = preg_replace(sprintf('/\'[^\']*\'(*SKIP)(*FAIL)|\b%s\b/i', $field), $alias, $column);
                }
                $columnName = $column;
            }

            if ($useSum) {
                $columns[$key] = sprintf('SUM(%s) as %s', $columnName, $key);
            } else {
                $columns[$key] = sprintf('%s as %s', $columnName, $key);
            }

            $rsm->addScalarResult($key, $key, 'float');
        }

        // create query
        $nativeQuery = $this->getEntityManager()
            ->createNativeQuery(
                sprintf('SELECT %s FROM (%s) tmp', implode(', ', $columns), $query->getSQL()),
                $rsm
            )
        ;

        // assign parameters
        foreach ($query->getParameters() as $key => $value) {
            $nativeQuery->setParameter($key + 1, $value->getValue());
        }

        return $nativeQuery->getResult();
    }

    /**
     * @param array  $criteria
     * @param string $field
     *
     * @return array|null
     */
    public function getMinMax(array $criteria, string $field): ?array
    {
        $queryBuilder = $this->createQueryBuilder('q')->select(sprintf('
            MIN(q.%s) AS min,
            MAX(q.%s) AS max
        ', $field, $field));

        $this->applyCriteria($queryBuilder, $criteria);

        try {
            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * @param array       $criteria
     * @param int         $page
     * @param int|null    $limit
     * @param string|null $sort
     * @param string      $groupBy
     *
     * @return Pagerfanta|bool
     */
    public function getGroupedData(array $criteria, int $page = 1, int $limit = null, string $sort = null, string $groupBy)
    {
        $queryBuilder = $this->createQueryBuilder('q');

        $this->applyGrouping($queryBuilder, $criteria, $groupBy);
        $this->applyCriteria($queryBuilder, $criteria);

        if ($sort) {
            list($sortBy, $sortDir) = explode('-', $sort, 2);

            $queryBuilder = $queryBuilder->orderBy($sortBy, $sortDir);
        }

        // set result mapping
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('total', 'total', 'integer');

        // create native query
        $nativeQuery = $this->getEntityManager()
            ->createNativeQuery(
                sprintf('SELECT COUNT(1) AS total FROM (%s) tmp', $queryBuilder->getQuery()->getSQL()),
                $rsm
            )
        ;

        // assign parameters
        foreach ($queryBuilder->getParameters() as $key => $value) {
            $nativeQuery->setParameter($key + 1, $value->getValue());
        }

        // get number of results
        $nbResults = (int) $nativeQuery->getSingleScalarResult();

        if (is_null($limit)) {
            $limit = self::DEFAULT_LIMIT;
        }

        // save query before adding limit
        /** @var Query $query */
        $query = $queryBuilder->getQuery();

        if (-1 !== $page) {
            $queryBuilder
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
            ;
        }

        /** @var array|\Traversable $result */
        $result = $queryBuilder->getQuery()->getResult();

        try {
            $paginator = new Pagerfanta(new FixedAdapter($nbResults, $result, $query));

            if (-1 !== $page) {
                $paginator->setMaxPerPage($limit);
                $paginator->setCurrentPage($page);
            }

            return $paginator;
        } catch (OutOfRangeCurrentPageException $e) {
            return false;
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array        $criteria
     * @param string       $groupBy
     */
    protected function applyGrouping(QueryBuilder $queryBuilder, array $criteria, string $groupBy): void
    {
        // do nothing
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array        $criteria
     */
    protected function applyCriteria(QueryBuilder $queryBuilder, array $criteria): void
    {
        foreach ($criteria as $key => $value) {
            $name = $key;

            $this->applyValue($queryBuilder, $name, $key, $value);
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string       $name
     * @param string       $field
     * @param mixed        $value
     * @param bool         $split
     * @param string       $alias
     */
    protected function applyValue(QueryBuilder $queryBuilder, string $name, string $key, $value, bool $split = false, string $alias = 'q'): void
    {
        if ($split) {
            if (is_string($value)) {
                $value = explode('|', $value);
            }
            if (!is_array($value)) {
                $value = [$value];
            }
        }

        if (is_array($value)) {
            if (isset($value['from']) || isset($value['to'])) {
                if (isset($value['from'])) {
                    $queryBuilder
                        ->andWhere(sprintf('%s.%s >= :%s', $alias, $key, sprintf('%s_from', $name)))
                        ->setParameter(sprintf('%s_from', $name), $value['from'])
                    ;
                }

                if (isset($value['to'])) {
                    $queryBuilder
                        ->andWhere(sprintf('%s.%s < :%s', $alias, $key, sprintf('%s_to', $name)))
                        ->setParameter(sprintf('%s_to', $name), $value['to'])
                    ;
                }
            } else {
                $queryBuilder
                    ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $key, $name))
                    ->setParameter($name, $value)
                ;
            }
        } elseif (is_object($value) || is_bool($value)) {
            $queryBuilder
                ->andWhere(sprintf('%s.%s = :%s', $alias, $key, $name))
                ->setParameter($name, $value)
            ;
        } elseif (is_null($value)) {
            $queryBuilder->andWhere(sprintf('%s.%s IS NULL', $alias, $key));
        } else {
            // removes all non-alphanumeric characters except whitespaces.
            $value = trim(preg_replace('/[[:space:]]+/', ' ', $value));

            if ('id' === $key) {
                $queryBuilder
                    ->andWhere(sprintf('%s.%s = :%s', $alias, $key, $name))
                    ->setParameter($name, $value)
                ;
            } else {
                $queryBuilder
                    ->andWhere(sprintf('%s.%s LIKE :%s', $alias, $key, $name))
                    ->setParameter($name, sprintf('%%%s%%', $value))
                ;
            }
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string|null  $sort
     *
     * @return string|null
     */
    protected function applySort(QueryBuilder $queryBuilder, string $sort = null): ?string
    {
        // do nothing

        return $sort;
    }

    /**
     * @param string $unit
     *
     * @return [\DateTimeInterface, \DateTimeInterface]|null
     */
    protected function getDateRange(string $unit): ?array
    {
        switch ($unit) {
            case 'today':
                $from = (new \DateTime())->setTime(0, 0, 0);
                $to = (new \DateTime('tomorrow'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'yesterday':
                $from = (new \DateTime('yesterday'))->setTime(0, 0, 0);
                $to = (new \DateTime())->setTime(0, 0, 0);

                return [$from, $to];

            case 'last_week':
                $from = (new \DateTime('previous week'))->setTime(0, 0, 0);
                $to = (new \DateTime('this week'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'this_week':
                $from = (new \DateTime('this week'))->setTime(0, 0, 0);
                $to = (new \DateTime('next week'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'last_month':
                $from = (new \DateTime('first day of previous month'))->setTime(0, 0, 0);
                $to = (new \DateTime('first day of this month'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'this_month':
                $from = (new \DateTime('first day of this month'))->setTime(0, 0, 0);
                $to = (new \DateTime('first day of next month'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'last_quarter':
                $from = (new \DateTime())->setTime(0, 0, 0);
                $to = (clone $from);

                $month = $from->format('n');
                if ($month < 4) {
                    $from->modify('first day of last year october');
                } elseif ($month > 3 && $month < 7) {
                    $from->modify('first day of january');
                } elseif ($month > 6 && $month < 10) {
                    $from->modify('first day of april');
                } elseif ($month > 9) {
                    $from->modify('first day of july');
                }

                if ($month < 4) {
                    $to->modify('last day of last year december');
                } elseif ($month > 3 && $month < 7) {
                    $to->modify('last day of march');
                } elseif ($month > 6 && $month < 10) {
                    $to->modify('last day of june');
                } elseif ($month > 9) {
                    $to->modify('last day of september');
                }
                $to->modify('next day');

                return [$from, $to];

            case 'this_quarter':
                $from = (new \DateTime())->setTime(0, 0, 0);
                $to = (clone $from);

                $month = $from->format('n');
                if ($month < 4) {
                    $from->modify('first day of january');
                } elseif ($month > 3 && $month < 7) {
                    $from->modify('first day of april');
                } elseif ($month > 6 && $month < 10) {
                    $from->modify('first day of july');
                } elseif ($month > 9) {
                    $from->modify('first day of october');
                }

                if ($month < 4) {
                    $to->modify('last day of march');
                } elseif ($month > 3 && $month < 7) {
                    $to->modify('last day of june');
                } elseif ($month > 6 && $month < 10) {
                    $to->modify('last day of september');
                } elseif ($month > 9) {
                    $to->modify('last day of december');
                }
                $to->modify('next day');

                return [$from, $to];

            case 'last_year':
                $from = (new \DateTime('january first day of previous year'))->setTime(0, 0, 0);
                $to = (new \DateTime('january first day of this year'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'this_year':
                $from = (new \DateTime('january first day of this year'))->setTime(0, 0, 0);
                $to = (new \DateTime('january first day of next year'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'last_7_days':
            case 'last_14_days':
            case 'last_30_days':
            case 'last_45_days':
            case 'last_60_days':
            case 'last_90_days':
            case 'last_180_days':
                $split = explode('_', $unit);

                $from = (new \DateTime(sprintf('-%d day', $split[1])))->setTime(0, 0, 0);
                $to = (new \DateTime())->setTime(0, 0, 0);

                return [$from, $to];
        }

        return null;
    }

    /**
     * @return User|null
     */
    protected function getUser(): ?User
    {
        if ($token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();

            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }
}
