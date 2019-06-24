<?php

namespace EWZ\SymfonyAdminBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use EWZ\SymfonyAdminBundle\Model\User;
use EWZ\SymfonyAdminBundle\Pagerfanta\Adapter\FixedAdapter;
use EWZ\SymfonyAdminBundle\Repository\Traits\PagerfantaTrait;
use EWZ\SymfonyAdminBundle\Util\DateTimeUtil;
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

        if ($includeAssociationNames) {
            $fieldNames = array_merge($fieldNames, $this->getAssociationNames());
        }

        return $fieldNames;
    }

    /**
     * @param bool $includeCollection
     *
     * @return array
     */
    public function getAssociationNames(bool $includeCollection = false): array
    {
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->getClassMetadata($this->getClass());

        $fieldNames = [];
        foreach ($classMetadata->getAssociationNames() as $assocName) {
            if (!$includeCollection && $classMetadata->isCollectionValuedAssociation($assocName)) {
                continue;
            }

            $fieldNames[] = $assocName;
        }

        return $fieldNames;
    }

    /**
     * @return array
     */
    public function getJoinNames(): array
    {
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->getClassMetadata($this->getClass());

        static $joinNames = [];

        if (empty($joinNames) && count($classMetadata->getAssociationMappings())) {
            $joinNames = [];

            foreach ($classMetadata->getAssociationMappings() as $assocMapping) {
                $joinNames[$assocMapping['fieldName']] = [$this->getRandomJoinName()];

                $subClassMetadata = $this
                    ->getEntityManager()
                    ->getClassMetadata($assocMapping['targetEntity']);

                foreach ($subClassMetadata->getAssociationNames() as $assocName) {
                    $joinNames[$assocMapping['fieldName']][$assocName] = $this->getRandomJoinName();
                }
            }
        }

        return $joinNames;
    }

    /**
     * @return string
     */
    public function getRandomJoinName(): string
    {
        $token = StringUtil::generatePassword(3, [
            StringUtil::PASSWORD_UPPER_CASE,
            StringUtil::PASSWORD_LOWER_CASE,
        ]);

        return sprintf('%s%s', $token, uniqid());
    }

    /**
     * @param string     $name
     * @param string|int $key
     *
     * @return string|null
     */
    public function getJoinName(string $name, $key = 0): ?string
    {
        return $this->getJoinNames()[$name][$key] ?? null;
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
        return $this->search($criteria, -1, null, null, true);
    }

    /**
     * @param array       $criteria
     * @param string|null $sort
     *
     * @return Pagerfanta|\Traversable|bool
     */
    public function searchAll(array $criteria, string $sort = null)
    {
        return $this->search($criteria, -1, null, $sort);
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

        // set result mapping
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
     * @param string|null $sort
     * @param string      $groupBy
     *
     * @return Pagerfanta|bool
     */
    public function getAllGroupedData(array $criteria, string $sort = null, string $groupBy)
    {
        return $this->getGroupedData($criteria, -1, null, $sort, $groupBy);
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

        // save query before adding limit
        /** @var Query $query */
        $query = $queryBuilder->getQuery();

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

        // mock parse function on $query to retrive SQL column names
        $reflectionClass = new \ReflectionClass(Query::class);
        $reflectionMethod = $reflectionClass->getMethod('_parse');
        $reflectionMethod->setAccessible(true);

        /** @var ParserResult $parser */
        $parser = $reflectionMethod->invoke($query);

        /** @var array $scalarMappings */
        $scalarMappings = $parser->getResultSetMapping()->scalarMappings;

        // set result mapping
        $rsm = new ResultSetMapping();
        foreach ($scalarMappings as $alias => $field) {
            $rsm->addScalarResult($alias, $field);
        }

        $orderBy = null;
        if ($sort) {
            list($sortBy, $sortDir) = array_reverse(array_map('strrev', explode('-', strrev($sort), 2)));

            $orderBy = sprintf('ORDER BY %s %s', $sortBy, $sortDir);

            // replace field in column formula
            foreach ($scalarMappings as $alias => $field) {
                $orderBy = preg_replace(sprintf('/\'[^\']*\'(*SKIP)(*FAIL)|\b%s\b/i', $field), $alias, $orderBy);
            }
        }

        $limitOffset = null;
        if (is_null($limit)) {
            $limit = self::DEFAULT_LIMIT;
        }
        if (-1 !== $page) {
            $limitOffset = sprintf('LIMIT %d, %d', ($page - 1) * $limit, $limit);
        }

        // create query
        $nativeQuery = $this->getEntityManager()
            ->createNativeQuery(
                sprintf('SELECT * FROM (%s) tmp %s %s', $query->getSQL(), $orderBy, $limitOffset),
                $rsm
            )
        ;

        // assign parameters
        foreach ($query->getParameters() as $key => $value) {
            $nativeQuery->setParameter($key + 1, $value->getValue());
        }

        /** @var array $result */
        $result = $nativeQuery->getResult();

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
     * @param array        $fieldNames
     */
    protected function applyCriteria(QueryBuilder $queryBuilder, array $criteria, array $fieldNames = []): void
    {
        foreach ($criteria as $key => $value) {
            $this->applyValue($queryBuilder, $key, $fieldNames[$key] ?? $key, $value);
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

        foreach ($this->getAssociationNames(true) as $assocName) {
            if (strlen($key) > strlen($assocName)
                && $assocName === substr($key, 0, strlen($assocName))
            ) {
                $key = lcfirst(substr($key, strlen($assocName)));
                $alias = $this->getJoinName($assocName);

                if (!in_array($alias, $queryBuilder->getAllAliases())) {
                    $queryBuilder->leftJoin(sprintf('q.%s', $assocName), $alias);
                }
            }
        }

        if (is_array($value)) {
            if (isset($value['unit'])) {
                if ($dateRange = DateTimeUtil::getDateRange($value['unit'])) {
                    $value = [
                        'from' => $dateRange[0],
                        'to' => $dateRange[1],
                    ];
                }
            }

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
            } elseif (isset($value['MemberOf'])) {
                unset($value['MemberOf']);

                /** @var Expr\OrX $orX */
                $orX = $queryBuilder->expr()->orX();

                foreach ($value as $k => $v) {
                    $orX->add($queryBuilder->expr()->isMemberOf(sprintf(':%s%d', $key, $k), sprintf('%s.%s', $alias, $key)));
                    $queryBuilder->setParameter(sprintf('%s%d', $key, $k), $v);
                }

                $queryBuilder->andWhere($orX);
            } elseif (isset($value['notIn'])) {
                unset($value['notIn']);

                $queryBuilder
                    ->andWhere(sprintf('%s.%s NOT IN (:%s)', $alias, $key, $name))
                    ->setParameter($name, $value)
                ;
            } else {
                $queryBuilder
                    ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $key, $name))
                    ->setParameter($name, $value)
                ;
            }
        } elseif (is_null($value)) {
            $queryBuilder->andWhere(sprintf('%s.%s IS NULL', $alias, $key));
        } elseif (!is_string($value)) {
            $queryBuilder
                ->andWhere(sprintf('%s.%s = :%s', $alias, $key, $name))
                ->setParameter($name, $value)
            ;
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
     * @param array        $fieldNames
     *
     * @return string|null
     */
    protected function applySort(QueryBuilder $queryBuilder, string $sort = null, array $fieldNames = []): ?string
    {
        if (!$sort) {
            return null;
        }

        list($sortBy, $sortDir) = explode('-', $sort, 2);

        $aliases = [];
        foreach ($this->getAssociationNames() as $assocName) {
            $aliases[$assocName] = $alias = $this->getJoinName($assocName);

            if (strlen($sortBy) > strlen($assocName)
                && $assocName === substr($sortBy, 0, strlen($assocName))
            ) {
                if (!in_array($alias, $queryBuilder->getAllAliases())) {
                    $queryBuilder->leftJoin(sprintf('q.%s', $assocName), $alias);
                }

                $sortBy = lcfirst(substr($sortBy, strlen($assocName)));
                $aliases[$sortBy] = $this->getJoinName($assocName, $sortBy);
            }
        }

        if (isset($aliases[$sortBy])) {
            $alias = $aliases[$sortBy];

            if (!in_array($alias, $queryBuilder->getAllAliases())) {
                $queryBuilder->leftJoin(sprintf('%s.%s', $parentAlias ?? 'q', $sortBy), $alias);
            }

            $sort = sprintf('%s-%s-%s', $fieldNames[$sortBy] ?? 'name', $sortDir, $alias);
        }

        return $sort;
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
