<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
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
            if (empty($value) || (\is_string($value) && 0 === \strlen($value))) {
                $value = null;
            }

            if ($preSetData instanceof \Closure) {
                $value = $preSetData->bindTo($this)($key, $value);
            }

            $method = sprintf('set%s', StringUtil::classify($key));
            if (method_exists($object, $method)) {
                $fieldMapping = $this->getRepository()->getFieldMapping($key);
                if (isset($fieldMapping['targetEntity'])) {
                    switch ($fieldMapping['type']) {
                        case ClassMetadataInfo::ONE_TO_MANY:
                        case ClassMetadataInfo::MANY_TO_MANY:
                            if ($value instanceof Collection) {
                                break;
                            }

                            $value = $value
                                ? new ArrayCollection(
                                    $this->managerRegistry
                                        ->getRepository($fieldMapping['targetEntity'])
                                        ->searchAll(['id' => $value])
                                )
                                : null;

                            break;

                        case ClassMetadataInfo::ONE_TO_ONE:
                        case ClassMetadataInfo::MANY_TO_ONE:
                        default:
                            $value = $value
                                ? $this->managerRegistry
                                    ->getRepository($fieldMapping['targetEntity'])
                                    ->find($value)
                                : null;
                    }
                } elseif (isset($fieldMapping['type'])) {
                    switch ($fieldMapping['type']) {
                        case Types::BOOLEAN:
                            $value = $value ? (bool) $value : false;
                            break;

                        case Types::SMALLINT:
                        case Types::INTEGER:
                            $value = $value ? (int) $value : null;
                            break;

                        case Types::DECIMAL:
                        case Types::FLOAT:
                            $value = $value ? (float) $value : null;
                            break;

                        case Types::DATETIME_MUTABLE:
                        case Types::DATETIMETZ_MUTABLE:
                        case Types::DATE_MUTABLE:
                        case Types::TIME_MUTABLE:
                            $value = $value ? new \DateTime($value) : null;
                            break;

                        case Types::DATETIME_IMMUTABLE:
                        case Types::DATETIMETZ_IMMUTABLE:
                        case Types::DATE_IMMUTABLE:
                        case Types::TIME_IMMUTABLE:
                            $value = $value ? new \DateTimeImmutable($value) : null;
                            break;

                        case Types::DATEINTERVAL:
                            $value = $value ? new \DateInterval($value) : null;
                            break;

                        case Types::ARRAY:
                        case Types::SIMPLE_ARRAY:
                        case Types::JSON:
                            if ($value && !\is_array($value)) {
                                $value = [$value];
                            }
                            break;
                    }
                }

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
                    $value = $value->format(sprintf('%s %s', $this->getUser()->getDateFormat(), $this->getUser()->getTimeFormat()));
                } elseif ($value instanceof Collection) {
                    $values = [];
                    foreach ($value as $v) {
                        $values[] = (string) $v;
                    }
                    $value = $values;
                } elseif (\is_string($value) || \is_object($value)) {
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
