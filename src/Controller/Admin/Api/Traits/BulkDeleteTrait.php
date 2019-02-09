<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;

trait BulkDeleteTrait
{
    /**
     * @param array         $objects
     * @param \Closure|null $preDelete
     * @param \Closure|null $postDelete
     *
     * @return JsonResponse
     */
    private function doBulkDelete(array $objects, $preDelete = null, $postDelete = null): JsonResponse
    {
        // skip empty selection
        if (empty($objects)) {
            return $this->json([
                'ok' => true,
                'total' => 0,
                'message' => $this->translator->trans('alert.bulk_delete_empty'),
            ]);
        }

        foreach ($objects as $index => $object) {
            if ($preDelete instanceof \Closure) {
                $preDelete->bindTo($this)($object);
            }

            // @see DeleteTrait::doDeleteFiles
            $this->doDeleteFiles($object);

            $this->getRepository()->remove($object, $index + 1 == count($objects));

            if ($postDelete instanceof \Closure) {
                $postDelete->bindTo($this)($object);
            }
        }

        return $this->json([
            'ok' => true,
            'total' => count($objects),
            'message' => $this->translator->trans('alert.bulk_delete', ['%total%' => count($objects)]),
        ]);
    }
}
