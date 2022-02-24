<?php

namespace EWZ\SymfonyAdminBundle\Doctrine;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use EWZ\SymfonyAdminBundle\Model\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Doctrine listener updating the canonical username and password fields.
 */
abstract class AbstractUserSubscriber implements EventSubscriberInterface
{
    /** @var PasswordHasherFactoryInterface */
    private $hasherFactory;

    /**
     * @param PasswordHasherFactoryInterface $hasherFactory
     */
    public function __construct(PasswordHasherFactoryInterface $hasherFactory)
    {
        $this->hasherFactory = $hasherFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof User) {
            $this->updateUserFields($object);
        }
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof User) {
            $usernameChanged = $args->hasChangedField('username');
            $emailChanged = $args->hasChangedField('email');
            $passwordChanged = $args->hasChangedField('plainPassword');

            $this->updateUserFields($object, $usernameChanged || $emailChanged, $passwordChanged);
            $this->recomputeChangeSet($args->getObjectManager(), $object);
        }
    }

    /**
     * Updates the user properties.
     *
     * @param User $user
     * @param bool $updateCanonicalFields
     * @param bool $updatePassword
     */
    protected function updateUserFields(User $user, bool $updateCanonicalFields = true, bool $updatePassword = true): void
    {
        if ($updateCanonicalFields) {
            $user->updateCanonicalFields();
        }

        if ($updatePassword) {
            $user->hashPassword($this->hasherFactory);
        }
    }

    /**
     * Recomputes change set for Doctrine implementations not doing it automatically after the event.
     *
     * @param ObjectManager $om
     * @param User          $user
     */
    private function recomputeChangeSet(ObjectManager $om, User $user): void
    {
        $meta = $om->getClassMetadata(\get_class($user));

        if ($om instanceof EntityManager) {
            $om->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $user);

            return;
        }

        if ($om instanceof DocumentManager) {
            $om->getUnitOfWork()->recomputeSingleDocumentChangeSet($meta, $user);
        }
    }
}
