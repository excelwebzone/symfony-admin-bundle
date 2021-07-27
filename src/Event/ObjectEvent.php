<?php

namespace EWZ\SymfonyAdminBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ObjectEvent extends Event
{
    /** @var object */
    private $object;

    /** @var array */
    private $entityChangeSet;

    /**
     * @param object $object
     * @param array  $changeSet
     */
    public function __construct($object, array $changeSet = [])
    {
        $this->object = $object;
        $this->entityChangeSet = $changeSet;
    }

    /**
     * Retrieves the associated object.
     *
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Retrieves the entity changeset.
     *
     * @return array
     */
    public function getEntityChangeSet(): array
    {
        return $this->entityChangeSet;
    }

    /**
     * Checks if field has a changeset.
     *
     * @param string $field
     *
     * @return bool
     */
    public function hasChangedField($field): bool
    {
        return isset($this->entityChangeSet[$field]);
    }

    /**
     * Gets the old value of the changeset of the changed field.
     *
     * @param string $field
     *
     * @return mixed
     */
    public function getOldValue($field)
    {
        $this->assertValidField($field);

        return $this->entityChangeSet[$field][0];
    }

    /**
     * Gets the new value of the changeset of the changed field.
     *
     * @param string $field
     *
     * @return mixed
     */
    public function getNewValue($field)
    {
        $this->assertValidField($field);

        return $this->entityChangeSet[$field][1];
    }

    /**
     * Sets the new value of this field.
     *
     * @param string $field
     * @param mixed  $value
     */
    public function setNewValue($field, $value): void
    {
        $this->assertValidField($field);

        $this->entityChangeSet[$field][1] = $value;
    }

    /**
     * Asserts the field exists in changeset.
     *
     * @param string $field
     *
     * @throws \InvalidArgumentException
     */
    private function assertValidField($field): void
    {
        if (!isset($this->entityChangeSet[$field])) {
            throw new \InvalidArgumentException(sprintf(
                'Field "%s" is not a valid field of the entity "%s" in PreUpdateEventArgs.',
                $field,
                \get_class($this->getObject())
            ));
        }
    }
}
