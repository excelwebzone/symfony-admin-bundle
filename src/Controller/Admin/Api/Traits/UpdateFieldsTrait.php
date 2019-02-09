<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

trait UpdateFieldsTrait
{
    /**
     * @param Request       $request
     * @param mixed         $object
     * @param \Closure|null $preSetData
     * @param \Closure|null $postSetData
     * @param \Closure|null $onCompleted
     *
     * @return JsonResponse
     */
    private function doUpdateFields(Request $request, $object, $preSetData = null, $postSetData = null, $onCompleted = null): JsonResponse
    {
        $updatedValue = null;
        $data = json_decode($request->getContent(), true);
        foreach ($data as $key => $value) {
            if (empty($value) || (is_string($value) && 0 === strlen($value))) {
                $value = null;
            }

            if ($preSetData instanceof \Closure) {
                $value = $preSetData->bindTo($this)($key, $value);
            }

            $method = sprintf('set%s', StringUtil::classify($key));
            if (method_exists($object, $method)) {
                $object->$method($value);
            }

            $errors = $this->validator->validate($object);
            if (count($errors) > 0) {
                return $this->json([
                    'ok' => false,
                    'error' => [
                        'message' => $errors[0]->getMessage(),
                    ],
                ]);
            }

            if ($postSetData instanceof \Closure) {
                try {
                    $postSetData->bindTo($this)($key, $object);
                } catch (\Exception $e) {
                    return $this->json([
                        'ok' => false,
                        'error' => [
                            'message' => $e->getMessage(),
                        ],
                    ]);
                }
            }

            $this->getRepository()->update($object);

            // get updated value
            $method = sprintf('get%s', StringUtil::classify($key));
            if (method_exists($object, $method)) {
                $updatedValue = $object->$method($value);
            }
        }

        $data = [
            'id' => $object->getId(),
            'label' => (string) $object,
        ];
        if (is_string($updatedValue)) {
            $data['updatedValue'] = $updatedValue;
        }
        if ($onCompleted instanceof \Closure) {
            $data = array_merge($data, $onCompleted->bindTo($this)($key, $object));
        }

        return $this->json(array_merge($data, [
            'ok' => true,
        ]));
    }
}
