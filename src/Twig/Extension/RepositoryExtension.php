<?php

namespace EWZ\SymfonyAdminBundle\Twig\Extension;

use Doctrine\Common\Persistence\ObjectManager;
use EWZ\SymfonyAdminBundle\Repository\AbstractRepository;
use Pagerfanta\Pagerfanta;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RepositoryExtension extends AbstractExtension
{
    /** @var ObjectManager */
    private $objectManager;

    /**
     * @param ObjectManager $objectManager
     */
    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('get_repository', [$this, 'getRepository']),
            new TwigFunction('get_search_count', [$this, 'countSearch']),
            new TwigFunction('get_search', [$this, 'search']),
            new TwigFunction('get_search_one', [$this, 'searchOne']),
            new TwigFunction('get_search_by_id', [$this, 'searchById']),
            new TwigFunction('get_grouped_data', [$this, 'getGroupedData']),
        ];
    }

    /**
     * @param string $class
     *
     * @return AbstractRepository|null
     */
    public function getRepository(string $class): ?AbstractRepository
    {
        return $this->objectManager->getRepository($class);
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
     * @param bool        $doCount
     *
     * @return Pagerfanta|\Traversable|int|bool
     */
    public function search(string $class, array $criteria, int $page = 1, int $limit = null, string $sort = null, bool $doCount = false)
    {
        return $this->getRepository($class)->search($criteria, $page, $limit, $sort, $doCount);
    }

    /**
     * @param string      $class
     * @param array       $criteria
     * @param string|null $sort
     *
     * @return mixed|null
     */
    public function searchOne(string $class, array $criteria, string $sort = null)
    {
        $result = $this->getRepository($class)->search($criteria, 1, 1, $sort);

        return $result[0] ?? null;
    }

    /**
     * @param string $class
     * @param mixed  $id
     *
     * @return mixed|null
     */
    public function searchById(string $class, $id)
    {
        return $this->getRepository($class)->find($id);
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
        return $this->getRepository($class)->search($criteria, $page, $limit, $sort, $groupBy);
    }
}
