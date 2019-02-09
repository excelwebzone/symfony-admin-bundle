<?php

namespace EWZ\SymfonyAdminBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class HashToKeyValueArrayTransformer implements DataTransformerInterface
{
    /** @var bool */
    private $ignoreEmptyValues;

    /**
     * @param bool $ignoreEmptyValues
     */
    public function __construct(bool $ignoreEmptyValues)
    {
        $this->ignoreEmptyValues = $ignoreEmptyValues;
    }

    /**
     * Doing the transformation here would be too late for the collection type to do it's resizing magic, so
     * instead it is done in the forms PRE_SET_DATA listener.
     *
     * {@inheritdoc}
     */
    public function transform($value)
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        $return = [];

        foreach ($value as $data) {
            if (['key', 'value'] != array_keys($data)) {
                throw new TransformationFailedException();
            }

            if (!$this->ignoreEmptyValues || !empty($data['value'])) {
                $return[] = $data;
            }
        }

        return $return;
    }
}
