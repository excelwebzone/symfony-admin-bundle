<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;

trait DeleteTrait
{
    /**
     * @param mixed         $object
     * @param \Closure|null $preDelete
     * @param \Closure|null $postDelete
     * @param \Closure|null $onCompleted
     *
     * @return JsonResponse
     */
    private function doDelete($object, $preDelete = null, $postDelete = null, $onCompleted = null): JsonResponse
    {
        if ($preDelete instanceof \Closure) {
            $preDelete->bindTo($this)($object);
        }

        $this->getRepository()->remove($object);

        if ($postDelete instanceof \Closure) {
            $postDelete->bindTo($this)($object);
        }

        $data = [
            'id' => $object->getId(),
            'label' => (string) $object,
        ];
        if ($onCompleted instanceof \Closure) {
            $data = array_merge($data, $onCompleted->bindTo($this)($object));
        }

        return $this->json(array_merge($data, [
            'ok' => true,
        ]));
    }
}
