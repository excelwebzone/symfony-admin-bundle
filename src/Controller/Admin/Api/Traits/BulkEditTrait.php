<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Symfony\Component\HttpFoundation\JsonResponse;

trait BulkEditTrait
{
    /**
     * @param array         $objects
     * @param array         $data
     * @param \Closure|null $preEdit
     * @param \Closure|null $postEdit
     *
     * @return JsonResponse
     */
    private function doBulkEdit(array $objects, array $data, $preEdit = null, $postEdit = null): JsonResponse
    {
        // skip empty selection
        if (empty($objects)) {
            return $this->json([
                'ok' => true,
                'total' => 0,
                'message' => $this->translator->trans('alert.bulk_edit_empty'),
            ]);
        }

        foreach ($objects as $index => $object) {
            if ($preEdit instanceof \Closure) {
                $preEdit->bindTo($this)($object);
            }

            foreach ($data as $key => $value) {
                $method = sprintf('set%s', StringUtil::classify($key));
                if (method_exists($object, $method)) {
                    $object->$method($value);
                }

                $errors = $this->validator->validate($object);
                if (\count($errors) > 0) {
                    return $this->json([
                        'ok' => false,
                        'error' => [
                            'message' => $errors[0]->getMessage(),
                        ],
                    ]);
                }
            }

            $this->getRepository()->update($object, $index + 1 == \count($objects));

            if ($postEdit instanceof \Closure) {
                $postEdit->bindTo($this)($object);
            }
        }

        return $this->json([
            'ok' => true,
            'total' => \count($objects),
            'message' => $this->translator->trans('alert.bulk_edit', ['%total%' => \count($objects)]),
        ]);
    }
}
