<?php

namespace EWZ\SymfonyAdminBundle\Form\DataTransformer;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ObjectToIdTransformer implements DataTransformerInterface
{
    /** @var RegistryInterface */
    protected $registry;

    /** @var string */
    protected $class;

    /**
     * @param RegistryInterface $registry
     * @param string            $class
     */
    public function __construct(RegistryInterface $registry, string $class)
    {
        $this->registry = $registry;
        $this->class = $class;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof $this->class) {
            throw new UnexpectedTypeException($value, $this->class);
        }

        return $value->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $object = $this->registry
                    ->getManagerForClass($this->class)
                    ->getRepository($this->class)
                    ->find($value);

        if (null === $object) {
            throw new TransformationFailedException(sprintf('Object from class %s with id "%s" not found', $this->class, $value));
        }

        return $object;
    }
}
