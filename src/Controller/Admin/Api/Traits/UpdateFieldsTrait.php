<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Type;
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
            if (empty($value) || (is_string($value) && 0 === strlen($value))) {
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
                            $value = $value
                                ? new ArrayCollection(
                                    $this->objectManager
                                        ->getRepository($fieldMapping['targetEntity'])
                                        ->searchAll(['id' => $value])
                                )
                                : null;

                            break;

                        case ClassMetadataInfo::ONE_TO_ONE:
                        case ClassMetadataInfo::MANY_TO_ONE:
                        default:
                            $value = $value
                                ? $this->objectManager
                                    ->getRepository($fieldMapping['targetEntity'])
                                    ->find($value)
                                : null;
                    }
                } elseif (isset($fieldMapping['type'])) {
                    switch ($fieldMapping['type']) {
                        case Type::BOOLEAN:
                            $value = $value ? boolval($value) : false;
                            break;

                        case Type::SMALLINT:
                        case Type::INTEGER:
                            $value = $value ? intval($value) : null;
                            break;

                        case Type::DECIMAL:
                        case Type::FLOAT:
                            $value = $value ? floatval($value) : null;
                            break;

                        case Type::DATETIME:
                        case Type::DATETIMETZ:
                        case Type::DATE:
                        case Type::TIME:
                            $value = $value ? new \DateTime($value) : null;
                            break;

                        case Type::DATETIME_IMMUTABLE:
                        case Type::DATETIMETZ_IMMUTABLE:
                        case Type::DATE_IMMUTABLE:
                        case Type::TIME_IMMUTABLE:
                            $value = $value ? new \DateTimeImmutable($value) : null;
                            break;

                        case Type::DATEINTERVAL:
                            $value = $value ? new \DateInterval($value) : null;
                            break;

                        case Type::TARRAY:
                        case Type::SIMPLE_ARRAY:
                        case Type::JSON_ARRAY:
                        case Type::JSON:
                            if ($value && !is_array($value)) {
                                $value = [$value];
                            }
                            break;
                    }
                }

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
                    $value = $value->format(sprintf('%s %s', $this->getUser()->getDateFormat(), $this->getUser()->getTimeFormat()));
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
