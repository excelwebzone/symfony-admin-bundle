<?php

namespace EWZ\SymfonyAdminBundle\Twig\Extension;

use EWZ\SymfonyAdminBundle\Repository\AbstractRepository;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RepositoryExtension extends AbstractExtension
{
    /** @var RegistryInterface */
    private $registry;

    /**
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('get_repository', [$this, 'getRepository']),
            new TwigFunction('search_count', [$this, 'countSearch']),
            new TwigFunction('search_data', [$this, 'search']),
            new TwigFunction('search_all', [$this, 'searchAll']),
            new TwigFunction('search_one', [$this, 'searchOne']),
            new TwigFunction('search_by_id', [$this, 'searchById']),
            new TwigFunction('search_grouped_data', [$this, 'getGroupedData']),
            new TwigFunction('search_all_grouped_data', [$this, 'getAllGroupedData']),
        ];
    }

    /**
     * @param string $class
     *
     * @return AbstractRepository|null
     */
    public function getRepository(string $class): ?AbstractRepository
    {
        return $this->registry
            ->getManagerForClass($class)
            ->getRepository($class);
    }

    /**
     * @param string $class
     * @param array  $criteria
     *
     * @return int
     */
    public function countSearch(string $class, array $criteria): int
    {
        return $this->getRepository($class)->countSearch($criteria);
    }

    /**
     * @param string      $class
     * @param array       $criteria
     * @param int         $page
     * @param int|null    $limit
     * @param string|null $sort
     *
     * @return Pagerfanta|\Traversable|int|bool
     */
    public function search(string $class, array $criteria, int $page = 1, int $limit = null, string $sort = null)
    {
        return $this->getRepository($class)->search($criteria, $page, $limit, $sort);
    }

    /**
     * @param string      $class
     * @param array       $criteria
     * @param string|null $sort
     *
     * @return Pagerfanta|\Traversable|bool
     */
    public function searchAll(string $class, array $criteria = [], string $sort = null)
    {
        return $this->getRepository($class)->searchAll($criteria, $sort);
    }

    /**
     * @param string      $class
     * @param array       $criteria
     * @param string|null $sort
     *
     * @return mixed|null
     */
    public function searchOne(string $class, array $criteria = [], string $sort = null)
    {
        return $this->getRepository($class)->searchOne($criteria, $sort);
    }

    /**
     * @param string $class
     * @param mixed  $id
     *
     * @return mixed|null
     */
    public function searchById(string $class, $id)
    {
        return $this->getRepository($class)->searchOne(['id' => $id]);
    }

    /**
     * @param string      $class
     * @param array       $criteria
     * @param int         $page
     * @param int|null    $limit
     * @param string|null $sort
     * @param string      $groupBy
     *
     * @return array
     */
    public function getGroupedData(string $class, array $criteria, int $page = 1, int $limit = null, string $sort = null, string $groupBy): array
    {
        return $this->getRepository($class)->getGroupedData($criteria, $page, $limit, $sort, $groupBy);
    }

    /**
     * @param string      $class
     * @param array       $criteria
     * @param string|null $sort
     * @param string      $groupBy
     *
     * @return array
     */
    public function getAllGroupedData(string $class, array $criteria, string $sort = null, string $groupBy): array
    {
        return $this->getRepository($class)->getAllGroupedData($criteria, $sort, $groupBy);
    }
}
