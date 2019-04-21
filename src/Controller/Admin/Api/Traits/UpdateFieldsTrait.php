<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Doctrine\Common\Collections\Collection;
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
        $fields = [];
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
                $value = $object->$method($value);

                if ($value instanceof \DateTimeInterface) {
                    $value = $value
                        ->setTimezone(new \DateTimeZone($this->getUser()->getTimezone()))
                        ->format(sprintf('%s H:i:s', $this->getUser()->getDateFormat()));
                } elseif ($value instanceof Collection) {
                    $values = [];
                    foreach ($value as $v) {
                        $values[] = (string) $v;
                    }
                    $value = $values;
                } elseif (is_string($value) || is_object($value)) {
                    $value = (string) $value;
                }

                $fields[$key] = $value;
            }
        }

        $data = [
            'id' => $object->getId(),
            'label' => (string) $object,
            'fields' => [],
        ];
        if ($onCompleted instanceof \Closure) {
            $data = array_merge($data, $onCompleted->bindTo($this)($key, $object));
        }

        $data['fields'] = array_merge($fields, $data['fields']);
        if (empty($data['fields'])) {
            unset($data['fields']);
        }

        return $this->json(array_merge($data, [
            'ok' => true,
        ]));
    }
}
