<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;

trait SearchTrait
{
    /**
     * @param array         $criteria
     * @param string        $template
     * @param int           $page
     * @param int|null      $limit
     * @param string|null   $sort
     * @param \Closure|null $onCompleted
     *
     * @return JsonResponse
     */
    private function doSearch(array $criteria, string $template, int $page = 1, int $limit = null, string $sort = null, $onCompleted = null): JsonResponse
    {
        $items = $this->getRepository()->search($criteria, $page, $limit, $sort);

        $html = $this->renderView($template, [
            'criteria' => $criteria,
            'items' => $items,
        ]);

        $data = [
            'html' => $html,
            'page' => $page,
            'count' => $items ? count($items->getCurrentPageResults()) : 0,
            'total' => $items ? $items->count() : 0,
        ];

        if ($onCompleted instanceof \Closure) {
            $data = array_merge($data, $onCompleted->bindTo($this)());
        }

        return $this->json(array_merge($data, [
            'ok' => true,
        ]));
    }

    /**
     * @param array         $criteria
     * @param \Closure|null $processData
     *
     * @return JsonResponse
     */
    private function doAutocomplete(array $criteria, $processData = null): JsonResponse
    {
        $objects = $this->getRepository()->search($criteria);

        $options = [];
        foreach ($objects as $object) {
            $value = (string) $object;
            if ($processData instanceof \Closure) {
                $value = $processData->bindTo($this)($object);
            }

            $options[(string) $object->getId()] = $value;
        }

        return $this->json([
            'ok' => true,
            'options' => $options,
            'count' => $objects ? count($objects->getCurrentPageResults()) : 0,
            'total' => $objects ? $objects->count() : 0,
        ]);
    }
}
