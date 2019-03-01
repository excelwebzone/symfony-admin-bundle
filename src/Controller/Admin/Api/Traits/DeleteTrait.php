<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;

trait DeleteTrait
{
    /**
     * @param mixed         $object
     * @param \Closure|null $preDelete
     * @param \Closure|null $postDelete
     *
     * @return JsonResponse
     */
    private function doDelete($object, $preDelete = null, $postDelete = null): JsonResponse
    {
        if ($preDelete instanceof \Closure) {
            $preDelete->bindTo($this)($object);
        }

        $this->getRepository()->remove($object);

        if ($postDelete instanceof \Closure) {
            $postDelete->bindTo($this)($object);
        }

        return $this->json([
            'ok' => true,
            'label' => (string) $object,
        ]);
    }
}
