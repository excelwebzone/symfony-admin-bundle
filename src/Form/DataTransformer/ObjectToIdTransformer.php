<?php

namespace EWZ\SymfonyAdminBundle\Form\DataTransformer;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ObjectToIdTransformer implements DataTransformerInterface
{
    /** @var ManagerRegistry */
    protected $managerRegistry;

    /** @var string */
    protected $class;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param string          $class
     */
    public function __construct(ManagerRegistry $managerRegistry, string $class)
    {
        $this->managerRegistry = $managerRegistry;
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

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $object = $this->managerRegistry
                    ->getManagerForClass($this->class)
                    ->getRepository($this->class)
                    ->find($value);

        if (null === $object) {
            throw new TransformationFailedException(sprintf('Object from class %s with id "%s" not found', $this->class, $value));
        }

        return $object;
    }
}
