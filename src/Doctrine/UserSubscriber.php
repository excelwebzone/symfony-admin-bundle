<?php

namespace EWZ\SymfonyAdminBundle\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use EWZ\SymfonyAdminBundle\Model\User;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * Doctrine listener updating the canonical username and password fields.
 */
class UserSubscriber implements EventSubscriber
{
    /** @var EncoderFactoryInterface */
    private $encoderFactory;

    /**
     * @param EncoderFactoryInterface $encoderFactory
     */
    public function __construct(EncoderFactoryInterface $encoderFactory)
    {
        $this->encoderFactory = $encoderFactory;
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
     * @param LifecycleEventArgs $args
     */
    public function preUpdate(LifecycleEventArgs $args): void
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
    private function updateUserFields(User $user, bool $updateCanonicalFields = true, bool $updatePassword = true): void
    {
        if ($updateCanonicalFields) {
            $user->updateCanonicalFields();
        }

        if ($updatePassword) {
            $user->hashPassword($this->encoderFactory);
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
        $meta = $om->getClassMetadata(get_class($user));

        if ($om instanceof EntityManager) {
            $om->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $user);

            return;
        }

        if ($om instanceof DocumentManager) {
            $om->getUnitOfWork()->recomputeSingleDocumentChangeSet($meta, $user);
        }
    }
}
