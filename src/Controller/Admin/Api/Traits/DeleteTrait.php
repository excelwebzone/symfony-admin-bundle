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

        $this->doDeleteFiles($object);
        $this->getRepository()->remove($object);

        if ($postDelete instanceof \Closure) {
            $postDelete->bindTo($this)($object);
        }

        return $this->json([
            'ok' => true,
            'label' => (string) $object,
        ]);
    }

    /**
     * @param mixed $object
     * @param bool  $andFlush
     */
    private function doDeleteFiles($object, $andFlush = false): void
    {
        if (method_exists($object, 'getPhoto') && $object->getPhoto()) {
            $this->fileUploader->delete($object->getPhoto());
        }

        if (method_exists($object, 'getFile') && $object->getFile()) {
            $this->fileUploader->delete($object->getFile());
        }

        /*
                if (method_exists($object, 'getFiles') && $object->getFiles()) {
                    foreach ($object->getFiles() as $file) {
                        $this->fileUploader->delete($file);
                    }
                }
        */
    }
}
