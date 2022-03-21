<?php

namespace EWZ\SymfonyAdminBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class StringToDateRangeTransformer implements DataTransformerInterface
{
    /** @var string */
    private $localeSeparator;

    /**
     * @param string $localeSeparator
     */
    public function __construct(string $localeSeparator)
    {
        $this->localeSeparator = $localeSeparator;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!\is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        return sprintf(
            '%s%s%s',
            $value[0]->format('Y-m-d H:i:s'),
            $this->localeSeparator,
            isset($value[1])
                ? $value[1]->format('Y-m-d H:i:s')
                : null
        );
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        list($from, $to) = explode($this->localeSeparator, $value);

        return [new \DateTime($from), $to ? new \DateTime($to) : null];
    }
}
